<?php

namespace KW\MashaFeedly\Control;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Service\MashaFeedlyEffectClient;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;

/** Liefert gecachte Effekte ausschließlich an angemeldete, für Feedly freigegebene Personen. */
class MashaFeedlyEffectsController extends Controller
{
    private static $allowed_actions = ['manifest', 'file', 'icons', 'icon', 'avatar'];
    private static $url_handlers = ['avatar/$ID/$Color' => 'avatar', 'icon/$ID/$Version/$Color' => 'icon', 'file/$ID/$Version/$Type' => 'file', 'icons' => 'icons', 'manifest' => 'manifest'];

    /** @param HTTPRequest $request Browser-Anfrage. @return HTTPResponse Privater Katalog oder Ablehnung. */
    public function manifest(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $manifest = Injector::inst()->get(MashaFeedlyEffectClient::class)->manifest();
            return $this->response(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json');
        } catch (\Throwable $error) {
            $status = $error instanceof \RuntimeException && $error->getCode() === 503 ? 503 : 502;
            return $this->response(json_encode([
                'error' => 'effect_provider_unavailable',
                'message' => 'Der Effekt-Anbieter ist momentan nicht verfügbar.',
            ], JSON_UNESCAPED_UNICODE), 'application/json', $status);
        }
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

    /** Liefert den Icon-Katalog nur bei aktivem externen Anbieter an berechtigte Mitglieder. @param HTTPRequest $request Browser-Anfrage. @return HTTPResponse Privater Katalog oder Ablehnung. */
    public function icons(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $catalogue = Injector::inst()->get(MashaFeedlyEffectClient::class)->avatarIcons();
            return $this->response(json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json');
        } catch (\Throwable $error) {
            $status = $error instanceof \RuntimeException && $error->getCode() === 503 ? 503 : 502;
            return $this->response(json_encode(['error' => 'profile_icons_unavailable', 'message' => 'Die Profil-Icon-Auswahl ist nicht verfügbar.'], JSON_UNESCAPED_UNICODE), 'application/json', $status);
        }
    }

    /** Liefert eine geprüfte Icon-Datei ausschließlich an freigegebene Mitglieder. @param HTTPRequest $request Browser-Anfrage. @return HTTPResponse Geschützte SVG-Datei oder Ablehnung. */
    public function icon(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $file = Injector::inst()->get(MashaFeedlyEffectClient::class)->avatarIcon(
                (string)$request->param('ID'), (string)$request->param('Version'), (string)$request->param('Color')
            );
            return $this->response($file['body'], $file['mime']);
        } catch (\Throwable $error) {
            return $this->response('', 'text/plain', $error->getCode() === 404 ? 404 : 502);
        }
    }

    /**
     * Gibt nur ein ausgewähltes, lokal gespeichertes Symbol an freigegebene Mitglieder aus.
     * @param HTTPRequest $request Symbolkennung und Farbvariante.
     * @return HTTPResponse Geschützte lokale SVG-Datei oder Ablehnung.
     */
    public function avatar(HTTPRequest $request): HTTPResponse
    {
        if ($denied = $this->deniedResponse($request)) return $denied;
        try {
            $id = (string)$request->param('ID');
            $color = (string)$request->param('Color');
            $client = Injector::inst()->get(MashaFeedlyEffectClient::class);
            try { $file = $client->storedAvatarIcon($id, $color); }
            catch (\RuntimeException $error) {
                if ($error->getCode() !== 404 || !Member::get()->filter('MashaFeedlyAvatarIcon', $id)->exists()) throw $error;
                $client->storeSelectedAvatarIcon($id);
                $file = $client->storedAvatarIcon($id, $color);
            }
            return $this->response($file['body'], $file['mime']);
        } catch (\Throwable $ignoredError) { return $this->response('', 'text/plain', 404); }
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
