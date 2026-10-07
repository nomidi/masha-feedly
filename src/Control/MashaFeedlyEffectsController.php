<?php

namespace KW\MashaFeedly\Control;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;

/** Liefert gecachte Effekte ausschließlich an angemeldete, für Feedly freigegebene Personen. */
class MashaFeedlyEffectsController extends Controller
{
    private static $allowed_actions = ['manifest', 'file'];
    private static $url_handlers = ['file/$ID/$Version/$Type' => 'file', 'manifest' => 'manifest'];

    /** @param HTTPRequest $request Browser-Anfrage. @return HTTPResponse Privater Katalog oder Ablehnung. */
    public function manifest(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $manifest = Injector::inst()->get(MashaFeedlyEffectClient::class)->manifest();
            return $this->response(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json');
        } catch (\Throwable $error) { return $this->response('Effekt-Anbieter nicht verfügbar.', 'text/plain', 502); }
    }

    /** @param HTTPRequest $request Browser-Anfrage mit ID, Hash und Typ. @return HTTPResponse Autorisierte Ressource oder Ablehnung. */
    public function file(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $file = Injector::inst()->get(MashaFeedlyEffectClient::class)->file((int)$request->param('ID'), (string)$request->param('Version'), (string)$request->param('Type'));
            return $this->response($file['body'], $file['mime']);
        } catch (\Throwable $error) { return $this->response('', 'text/plain', $error->getCode() === 404 ? 404 : 502); }
    }

    /** @param HTTPRequest $request Aktuelle Sitzung. @return HTTPResponse|null Ablehnung vor jedem Cache-Zugriff. */
    private function deniedResponse(HTTPRequest $request): ?HTTPResponse
    {
        if (!MashaFeedlyConfigExtension::canUse(Security::getCurrentUser())) return $this->response('Zugriff verweigert.', 'text/plain', 403);
        if (!in_array($request->httpMethod(), ['GET', 'HEAD'], true)) return $this->response('', 'text/plain', 405)->addHeader('Allow', 'GET, HEAD');
        return null;
    }

    /** @param string $body Antwortinhalt. @param string $mime MIME-Typ. @param int $status HTTP-Status. @return HTTPResponse Nicht öffentlich cachebare Antwort. */
    private function response(string $body, string $mime, int $status = 200): HTTPResponse
    {
        return HTTPResponse::create($body, $status)->addHeader('Content-Type', $mime)->addHeader('Cache-Control', 'private, no-store')
            ->addHeader('Vary', 'Cookie')->addHeader('X-Content-Type-Options', 'nosniff')->addHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }
}
