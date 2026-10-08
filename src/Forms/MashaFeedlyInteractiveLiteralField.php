<?php

namespace KW\MashaFeedly\Forms;

use SilverStripe\Forms\LiteralField;

/**
 * Stellt selbst gerenderte Profilaktionen bei einer Silverstripe-Schreibsperre still.
 */
class MashaFeedlyInteractiveLiteralField extends LiteralField
{
    /**
     * Erzeugt eine schreibgeschützte Fassung und deaktiviert darin enthaltene Bedienelemente.
     *
     * @return static
     */
    public function performReadonlyTransformation()
    {
        $clone = clone $this;
        $content = (string)$clone->getContent();
        $content = preg_replace_callback(
            '/<button\b([^>]*)>/i',
            static function (array $matches): string {
                if (preg_match('/\sdisabled(?:\s|=|>)/i', $matches[1])) {
                    return $matches[0];
                }

                return '<button' . $matches[1] . ' disabled aria-disabled="true">';
            },
            $content
        ) ?? $content;
        $content = preg_replace_callback(
            '/<(div|section)\b([^>]*\bdata-masha-feedly-avatar-icons\b[^>]*)>/i',
            static function (array $matches): string {
                if (preg_match('/\sinert(?:\s|=|>)/i', $matches[2])) {
                    return $matches[0];
                }

                return '<' . $matches[1] . $matches[2] . ' inert aria-disabled="true">';
            },
            $content
        ) ?? $content;

        $clone->setContent($content);
        $clone->setReadonly(true);

        return $clone;
    }
}
