<?php

namespace KW\MashaFeedly\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\i18n\i18n;

/**
 * Datenobjekt für einen Kommentar zu einem Masha-Feedly-Eintrag.
 *
 * @property int $ID Datenbank-ID des Kommentars.
 * @property string $AuthorName Name der kommentierenden Person.
 * @property string $CommentText Text des Kommentars.
 * @property bool $IsApproved Gibt an, ob der Kommentar freigegeben ist.
 * @property int $EntryID ID des zugehörigen Eintrags.
 * @property MashaFeedlyEntry $Entry Zugehöriger Eintrag.
 * @property string $Created Erstellungszeitpunkt.
 * @property string $LastEdited Zeitpunkt der letzten Änderung.
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyComment extends DataObject
{
    private static $table_name = 'MashaFeedlyComment';

    private static $db = [
        'AuthorName' => 'Varchar(120)',
        'CommentText' => 'Text',
        'IsApproved' => 'Boolean',
        'WasEdited' => 'Boolean',
    ];

    private static $has_one = [
        'Entry' => MashaFeedlyEntry::class,
        'AuthorMember' => Member::class,
    ];

    private static $has_many = [
        'Attachments' => MashaFeedlyAttachment::class,
    ];

    private static $summary_fields = [
        'AuthorName' => 'AuthorName',
        'CommentText' => 'CommentText',
        'IsApproved' => 'IsApproved',
        'Created' => 'Created',
    ];

    private static $defaults = [
        'IsApproved' => false,
        'WasEdited' => false,
    ];

    /**
     * Erstellt die im CMS bearbeitbaren Kommentarfelder.
     *
     * @return FieldList CMS-Felder für Name, Text und Freigabe.
     */
    public function getCMSFields(): FieldList
    {
        return FieldList::create(
            TextField::create('AuthorName', i18n::_t('KW\\MashaFeedly\\Translations.FIELD_COMMENT_AUTHOR', 'Name')),
            TextareaField::create('CommentText', i18n::_t('KW\\MashaFeedly\\Translations.FIELD_COMMENT', 'Kommentar')),
            $this->dbObject('IsApproved')->scaffoldFormField('IsApproved', ['title' => i18n::_t('KW\\MashaFeedly\\Translations.FIELD_APPROVED', 'Freigegeben')])
        );
    }

    /** Liefert lokalisierte Spaltenüberschriften für die Kommentarübersicht. */
    public function summaryFields()
    {
        $fields = parent::summaryFields();
        $fields['AuthorName'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_COMMENT_AUTHOR', 'Name');
        $fields['CommentText'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_COMMENT', 'Kommentar');
        $fields['IsApproved'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_APPROVED', 'Freigegeben');
        $fields['Created'] = i18n::_t('KW\\MashaFeedly\\Translations.FIELD_CREATED', 'Erstellt');
        return $fields;
    }
}
