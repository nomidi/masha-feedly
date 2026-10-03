<?php


namespace KW\MashaFeedly\Control;

use KW\MashaFeedly\Extension\MashaFeedlyConfigExtension;
use KW\MashaFeedly\Model\MashaFeedlyCategory;
use KW\MashaFeedly\Model\MashaFeedlyComment;
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use KW\MashaFeedly\Model\MashaFeedlyEntryHistory;
use KW\MashaFeedly\Model\MashaFeedlyEntryRead;
use KW\MashaFeedly\Model\MashaFeedlyEntryRelation;
use KW\MashaFeedly\Model\MashaFeedlyPriority;
use KW\MashaFeedly\Model\MashaFeedlySavedView;
use KW\MashaFeedly\Service\MashaFeedlyAttachmentService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Assets\Image;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Security\Permission;
use SilverStripe\i18n\i18n;

/** Speichert Einträge, die Mitglieder direkt im Masha-Feedly-Seitenwidget erfassen.
 *
 * @package MashaFeedly
 * @author Kooperative Web
 * @license MIT
 * @version 0.1.0
 */
class MashaFeedlyEntryController extends Controller
{
    private static $allowed_actions = ['index', 'createEntry', 'listEntries', 'updateEntry', 'findSimilarEntries', 'completeOnboarding', 'restartOnboarding', 'savedViews', 'saveView', 'deleteView', 'markEntryRead'];

    /** Markiert einen Eintrag für das angemeldete, berechtigte Mitglied als gelesen. */
    public function markEntryRead(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $entry = MashaFeedlyEntry::get()->byID((int)$request->postVar('EntryID'));
        if (!$entry) {
            return $this->respond(['success' => false, 'message' => $this->translate('ENTRY_NOT_FOUND', 'Der Eintrag wurde nicht gefunden.')], 404);
        }
        MashaFeedlyEntryRead::markAsSeen($entry, $member);
        $activityCounts = MashaFeedlyEntryRead::unreadActivityCounts($member);
        return $this->respond([
            'success' => true,
            'unreadCount' => $activityCounts['entries'],
            'unreadCommentCount' => $activityCounts['comments'],
        ]);
    }

    /** Liefert ausschließlich die persönlichen Ansichten des angemeldeten Masha-Mitglieds. */
    public function savedViews(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        return $this->respond(['success' => true, 'views' => $this->memberSavedViews($member)]);
    }

    /** Speichert oder aktualisiert eine persönliche Kombination aus Liste, Kategorie und Priorität. */
    public function saveView(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $title = trim((string)$request->postVar('Title'));
        $mode = (string)$request->postVar('Mode');
        $categoryID = max(0, (int)$request->postVar('CategoryID'));
        $priorityID = max(0, (int)$request->postVar('PriorityID'));
        if ($title === '' || mb_strlen($title) > 60 || !in_array($mode, ['all', 'open', 'closed', 'page', 'page-open', 'mine', 'feedback'], true)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SAVED_VIEW_INVALID', 'Bitte gib einen Namen und gültige Filter an.')], 400);
        }
        if (($categoryID && !MashaFeedlyCategory::get()->byID($categoryID)) || ($priorityID && !MashaFeedlyPriority::get()->byID($priorityID))) {
            return $this->respond(['success' => false, 'message' => $this->translate('SAVED_VIEW_INVALID', 'Bitte gib einen Namen und gültige Filter an.')], 400);
        }
        $views = $this->memberSavedViews($member);
        $viewID = trim((string)$request->postVar('ViewID'));
        $savedView = null;
        if ($viewID !== '') {
            $savedView = MashaFeedlySavedView::get()->filter([
                'ID' => max(0, (int)$viewID),
                'MemberID' => (int)$member->ID,
            ])->first();
            if (!$savedView) {
                return $this->respond(['success' => false, 'message' => $this->translate('SAVED_VIEW_NOT_FOUND', 'Diese Ansicht wurde nicht gefunden.')], 404);
            }
        } elseif (count($views) >= 20) {
            return $this->respond(['success' => false, 'message' => $this->translate('SAVED_VIEW_LIMIT', 'Du kannst höchstens 20 Ansichten speichern.')], 400);
        }
        if (!$savedView) {
            $savedView = MashaFeedlySavedView::create();
        }
        $savedView->Title = $title;
        $savedView->Mode = $mode;
        $savedView->CategoryID = $categoryID;
        $savedView->PriorityID = $priorityID;
        $savedView->MemberID = (int)$member->ID;
        $savedView->write();
        $view = $this->savedViewArray($savedView);
        return $this->respond(['success' => true, 'view' => $view, 'views' => $this->memberSavedViews($member)]);
    }

    /** Löscht ausschließlich eine Ansicht aus der persönlichen Liste des angemeldeten Mitglieds. */
    public function deleteView(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $viewID = trim((string)$request->postVar('ViewID'));
        $savedView = MashaFeedlySavedView::get()->filter([
            'ID' => max(0, (int)$viewID),
            'MemberID' => (int)$member->ID,
        ])->first();
        if (!$savedView) {
            return $this->respond(['success' => false, 'message' => $this->translate('SAVED_VIEW_NOT_FOUND', 'Diese Ansicht wurde nicht gefunden.')], 404);
        }
        $savedView->delete();
        return $this->respond(['success' => true, 'views' => $this->memberSavedViews($member)]);
    }

    /** Dekodiert validierte Ansichten aus dem Profilfeld. */
    private function memberSavedViews(Member $member): array
    {
        $views = [];
        foreach (MashaFeedlySavedView::get()->filter('MemberID', (int)$member->ID)->sort('ID ASC') as $view) {
            $views[] = $this->savedViewArray($view);
        }
        return $views;
    }

    /** Formatiert eine persönliche Ansicht für die JSON-Schnittstelle. */
    private function savedViewArray(MashaFeedlySavedView $view): array
    {
        return [
            'id' => (string)$view->ID,
            'title' => (string)$view->Title,
            'mode' => (string)$view->Mode,
            'categoryID' => (int)$view->CategoryID,
            'priorityID' => (int)$view->PriorityID,
        ];
    }

    /** Sperrt auch den direkten Aufruf der Controller-Basisroute für nicht freigegebene Mitglieder. */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if ($request->isGET()) {
            return $this->respond(['success' => true, 'views' => $this->memberSavedViews($member)]);
        }
        if ($request->isPOST()) {
            if (!SecurityToken::inst()->checkRequest($request)) {
                return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_DU', 'Deine Sitzung ist abgelaufen.')], 400);
            }
            return match (strtolower(trim((string)$request->postVar('ViewAction')))) {
                'save' => $this->saveView($request),
                'delete' => $this->deleteView($request),
                default => $this->respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400),
            };
        }
        return $this->respond(['success' => false, 'message' => 'Bitte verwende GET oder POST.'], 405);
    }

    /** Markiert die abbrechbare Ersteinführung für das aktuelle Mitglied als erledigt. */
    public function completeOnboarding(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $member->MashaFeedlyOnboardingCompleted = true;
        $member->MashaFeedlyShowOnboarding = false;
        $member->write();
        return $this->respond(['success' => true]);
    }

    /** Setzt die Einführung für ein berechtigtes Mitglied zurück und startet sie erneut. */
    public function restartOnboarding(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $member->MashaFeedlyOnboardingCompleted = false;
        $member->MashaFeedlyShowOnboarding = true;
        $member->write();
        return $this->respond(['success' => true]);
    }

    /** Ändert ausschließlich Status und Zuständigkeiten eines bestehenden Eintrags. */
    public function updateEntry(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'SEND_POST_SIE' : 'SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'SESSION_EXPIRED_UPDATE_SIE' : 'SESSION_EXPIRED_UPDATE_DU', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $entry = MashaFeedlyEntry::get()->byID((int)$request->postVar('EntryID'));
        if (!$entry) {
            return $this->respond(['success' => false, 'message' => $this->translate('ENTRY_NOT_FOUND', 'Der Eintrag wurde nicht gefunden.')], 404);
        }
        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $uploads = $_FILES['Attachments'] ?? [];
        if ($uploadError = MashaFeedlyAttachmentService::validateUploads($uploads)) {
            return $this->respond(['success' => false, 'message' => $uploadError], 400);
        }
        $category = MashaFeedlyCategory::get()->byID((int)$request->postVar('CategoryID'));
        if (!$category) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'INVALID_STATUS_SIE' : 'INVALID_STATUS_DU', 'Bitte wähle einen gültigen Status.')], 400);
        }
        $sentToFeedback = false;
        $previousCategoryKey = (string)$entry->Category()->SystemKey;
        $wasWaitingForFeedback = $previousCategoryKey === 'feedback';
        $wasAlreadyClosed = (bool)$entry->Category()->IsClosed;
        $creatorMemberID = $entry->creatorMemberID();
        if ((string)$category->SystemKey === 'done'
            && $creatorMemberID > 0
            && $creatorMemberID !== (int)$member->ID
        ) {
            $feedbackCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
            if (!$feedbackCategory) {
                return $this->respond(['success' => false, 'message' => $this->translate(
                    'FEEDBACK_CATEGORY_MISSING',
                    'Der Eintrag kann nicht zur Bestätigung übergeben werden, weil die Kategorie „Feedback“ fehlt.'
                )], 409);
            }
            $category = $feedbackCategory;
            $sentToFeedback = true;
        }
        $priorityValue = $request->postVar('PriorityID');
        $priority = $priorityValue === null || $priorityValue === ''
            ? ($entry->Priority()->exists() ? $entry->Priority() : MashaFeedlyPriority::defaultPriority())
            : MashaFeedlyPriority::get()->byID((int)$priorityValue);
        if (!$priority || !$priority->exists()) {
            return $this->respond(['success' => false, 'message' => $this->translate('INVALID_PRIORITY', 'Bitte wählen Sie eine gültige Priorität.')], 400);
        }
        $newAttachments = MashaFeedlyAttachmentService::attachUploads($uploads, $entry);
        $this->recordAttachmentHistory($entry, $newAttachments, $member);
        $oldAssigneeIDs = array_map('intval', $entry->AssignedMembers()->column('ID'));
        $oldAssigneeNames = $this->memberNames($oldAssigneeIDs);
        $entry->CategoryID = (int)$category->ID;
        $entry->PriorityID = (int)$priority->ID;
        $entry->write();
        $assignedIDs = MashaFeedlyConfigExtension::normalizeMemberIDs((array)$request->postVar('AssignedMemberIDs'));
        $allowedIDs = MashaFeedlyConfigExtension::memberIDs();
        $newAssigneeIDs = array_values(array_intersect($assignedIDs, $allowedIDs));
        $entry->AssignedMembers()->setByIDList($newAssigneeIDs);
        $oldRelations = $this->relationsSignature($entry);
        $relationType = (string)$request->postVar('RelationType');
        if (!in_array($relationType, ['blocked_by', 'duplicate_of', 'related'], true)) {
            $relationType = 'related';
        }
        $relatedInput = $request->postVar('RelatedEntryIDs') ?? $request->postVar('RelatedEntryIDs[]') ?? [];
        $relatedIDs = array_values(array_unique(array_filter(array_map('intval', (array)$relatedInput), static fn(int $id): bool => $id > 0 && $id !== (int)$entry->ID)));
        $relatedEntries = [];
        foreach ($relatedIDs as $relatedID) {
            $relatedEntry = MashaFeedlyEntry::get()->byID($relatedID);
            if ($relatedEntry) {
                $relatedEntries[] = $relatedEntry;
            }
        }
        $existingRelations = [];
        foreach ($entry->Relations()->toArray() as $existingRelation) {
            $existingRelations[(int)$existingRelation->RelatedEntryID] = $existingRelation;
        }
        foreach ($relatedEntries as $relatedEntry) {
            $relatedEntryID = (int)$relatedEntry->ID;
            if (isset($existingRelations[$relatedEntryID])) {
                $existingRelation = $existingRelations[$relatedEntryID];
                unset($existingRelations[$relatedEntryID]);
                if ((string)$existingRelation->LinkType !== $relationType) {
                    $existingRelation->LinkType = $relationType;
                    $existingRelation->write();
                }
                continue;
            }
            MashaFeedlyEntryRelation::create([
                'EntryID' => (int)$entry->ID,
                'RelatedEntryID' => $relatedEntryID,
                'LinkType' => $relationType,
            ])->write();
        }
        foreach ($existingRelations as $removedRelation) {
            $removedRelation->delete();
        }
        $closedDuplicateCount = 0;
        if ((string)$entry->Category()->SystemKey === 'done') {
            $doneCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'done')->first();
            if ($doneCategory) {
                $closedDuplicateCount = $this->closeDuplicateEntries($entry, $doneCategory);
            }
        }
        $newRelations = $this->relationsSignature($entry);
        if ($oldRelations !== $newRelations) {
            MashaFeedlyEntryHistory::record($entry, 'relations', $oldRelations, $newRelations, $member);
        }
        sort($oldAssigneeIDs);
        $sortedNewAssigneeIDs = array_map('intval', $newAssigneeIDs);
        sort($sortedNewAssigneeIDs);
        if ($oldAssigneeIDs !== $sortedNewAssigneeIDs) {
            MashaFeedlyEntryHistory::record(
                $entry,
                'assignees',
                implode(', ', $oldAssigneeNames),
                implode(', ', $this->memberNames($newAssigneeIDs)),
                $member
            );
        }
        $message = $sentToFeedback
            ? $this->translate('EDIT_WAITING_FOR_CREATOR', 'Der Eintrag wartet jetzt auf die Freigabe durch die erstellende Person.')
            : $this->translate('EDIT_SAVE_SUCCESS', 'Status, Priorität, Zuständigkeiten und Anhänge wurden gespeichert.');
        if ($closedDuplicateCount > 0) {
            $duplicateMessage = $closedDuplicateCount === 1
                ? $this->translate('EDIT_DUPLICATE_CLOSED_ONE', 'Ein Duplikat wurde ebenfalls abgeschlossen.')
                : str_replace(
                    '{count}',
                    (string)$closedDuplicateCount,
                    $this->translate('EDIT_DUPLICATES_CLOSED_MANY', '{count} Duplikate wurden ebenfalls abgeschlossen.')
                );
            $message .= ' ' . $duplicateMessage;
        }
        return $this->respond([
            'success' => true,
            'message' => $message,
            'closedDuplicateCount' => $closedDuplicateCount,
            'categoryID' => (int)$entry->CategoryID,
            'categoryTitle' => (string)$entry->Category()->Title,
            'categoryIsClosed' => (bool)$entry->Category()->IsClosed,
            'celebrateCompletion' => (string)$entry->Category()->SystemKey === 'done'
                && !$wasAlreadyClosed
                && ($wasWaitingForFeedback || ($creatorMemberID > 0 && $creatorMemberID === (int)$member->ID)),
            'priorityID' => (int)$entry->PriorityID,
            'priorityTitle' => (string)$entry->Priority()->Title,
            'priorityColor' => (string)$entry->Priority()->Color,
            'priorityIconType' => (string)$entry->Priority()->IconType,
            'attachments' => array_map(static function ($attachment): array {
                $file = $attachment->File();
                return [
                    'name' => (string)$attachment->OriginalName,
                    'mimeType' => (string)$attachment->MimeType,
                    'url' => $file && $file->exists() ? (string)$file->getURL() : '',
                ];
            }, $entry->Attachments()->toArray()),
            'history' => MashaFeedlyEntryHistory::dataForEntry($entry),
            'relations' => $this->relationsData($entry),
        ]);
    }

    /** Findet ähnliche offene Einträge derselben Seite für den Duplikat-Hinweis beim Schreiben. */
    public function findSimilarEntries(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate('SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate('SESSION_EXPIRED_UPDATE_SIE', 'Deine Sitzung ist abgelaufen.')], 400);
        }
        $content = mb_substr(trim(strip_tags((string)$request->postVar('Content'))), 0, 500);
        $pageURL = $this->safePageURL((string)$request->postVar('PageURL'));
        if (mb_strlen($content) < 12 || $pageURL === '') {
            return $this->respond(['success' => true, 'matches' => []]);
        }
        $openCategoryIDs = MashaFeedlyCategory::get()->filter('IsClosed', false)->column('ID');
        $candidates = $openCategoryIDs
            ? MashaFeedlyEntry::get()->filter(['PageURL' => $pageURL, 'CategoryID' => $openCategoryIDs])->sort('EntryDate DESC, ID DESC')->limit(60)
            : MashaFeedlyEntry::get()->filter('ID', 0);
        $matches = [];
        $normalizedContent = $this->normalizeForSimilarity($content);
        foreach ($candidates as $candidate) {
            $candidateText = mb_substr(trim(strip_tags(html_entity_decode((string)$candidate->Content, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 500);
            $normalizedCandidate = $this->normalizeForSimilarity($candidateText);
            if ($normalizedCandidate === '') {
                continue;
            }
            similar_text($normalizedContent, $normalizedCandidate, $percentage);
            $tokens = array_values(array_unique(array_filter(explode(' ', $normalizedContent), static fn(string $word): bool => mb_strlen($word) > 3)));
            $candidateTokens = array_fill_keys(explode(' ', $normalizedCandidate), true);
            $overlap = $tokens ? count(array_filter($tokens, static fn(string $word): bool => isset($candidateTokens[$word]))) / count($tokens) * 100 : 0;
            $score = (int)round(max($percentage, $overlap));
            if ($score < 20) {
                continue;
            }
            $matches[] = [
                'id' => (int)$candidate->ID,
                'title' => (string)$candidate->Title,
                'content' => mb_strimwidth($candidateText, 0, 180, '…'),
                'categoryTitle' => (string)$candidate->Category()->Title,
                'priorityTitle' => (string)$candidate->Priority()->Title,
                'priorityColor' => (string)$candidate->Priority()->Color,
                'score' => $score,
            ];
        }
        usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return $this->respond(['success' => true, 'matches' => array_slice($matches, 0, 3)]);
    }

    /** Liefert freigegebene Einträge für die aktuelle Seite oder alle persönlichen Zuweisungen. */
    public function listEntries(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate('NO_PERMISSION', 'Keine Berechtigung.')], 403);
        }

        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();

        $requestedPageURL = trim((string)$request->getVar('PageURL'));
        $pageURL = $this->safePageURL($requestedPageURL);
        $pageURLs = array_values(array_unique(array_filter([$pageURL, $requestedPageURL])));
        $pageEntries = $pageURLs
            ? MashaFeedlyEntry::get()->filter('PageURL', $pageURLs)
            : MashaFeedlyEntry::get()->filter('ID', 0);
        $allEntries = MashaFeedlyEntry::get();
        $mineEntries = MashaFeedlyEntry::get()->filter('AssignedMembers.ID', (int)$member->ID);
        $categories = [];
        $closedCategoryIDs = [];
        $openCategoryIDs = [];
        foreach (MashaFeedlyCategory::get()->sort('Sort ASC, Title ASC') as $category) {
            $isClosed = (bool)$category->IsClosed;
            $categories[] = [
                'id' => (int)$category->ID,
                'title' => (string)$category->Title,
                'isClosed' => $isClosed,
            ];
            if ($isClosed) {
                $closedCategoryIDs[] = (int)$category->ID;
            } else {
                $openCategoryIDs[] = (int)$category->ID;
            }
        }
        $pageOpenEntries = $openCategoryIDs
            ? $pageEntries->filter('CategoryID', $openCategoryIDs)
            : MashaFeedlyEntry::get()->filter('ID', 0);
        $unreadEntryIDs = MashaFeedlyEntryRead::unreadEntryIDs($member);
        $requestedMode = (string)$request->getVar('mode');
        $mode = in_array($requestedMode, ['all', 'open', 'closed', 'page', 'page-open', 'mine', 'feedback', 'unread'], true) ? $requestedMode : 'page';
        $entries = match ($mode) {
            'all' => $allEntries,
            'open' => $openCategoryIDs
                ? MashaFeedlyEntry::get()->filter('CategoryID', $openCategoryIDs)
                : MashaFeedlyEntry::get()->filter('ID', 0),
            'closed' => $closedCategoryIDs
                ? MashaFeedlyEntry::get()->filter('CategoryID', $closedCategoryIDs)
                : MashaFeedlyEntry::get()->filter('ID', 0),
            'mine' => $mineEntries,
            'page-open' => $pageOpenEntries,
            'feedback' => ($feedbackCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first())
                ? MashaFeedlyEntry::get()->filter('CategoryID', (int)$feedbackCategory->ID)
                : MashaFeedlyEntry::get()->filter('ID', 0),
            'unread' => MashaFeedlyEntry::get()->filter('ID', $unreadEntryIDs ?: [0]),
            default => $pageEntries,
        };
        $payload = [];
        foreach ($entries->sort(['Category.Sort' => 'ASC', 'Priority.Sort' => 'ASC', 'EntryDate' => 'DESC', 'ID' => 'DESC']) as $entry) {
            $entryData = $this->entryData($entry, $member);
            $entryData['isUnread'] = in_array((int)$entry->ID, $unreadEntryIDs, true);
            $payload[] = $entryData;
        }

        // Erledigte und archivierte Einträge zählen nicht mehr als offene Fehler.
        $openCount = 0;
        $pageOpenCount = 0;
        $feedbackCategory = MashaFeedlyCategory::get()->filter('SystemKey', 'feedback')->first();
        $feedbackCount = $feedbackCategory && !(bool)$feedbackCategory->IsClosed
            ? MashaFeedlyEntry::get()->filter('CategoryID', (int)$feedbackCategory->ID)->count()
            : 0;
        $pageEntryIDs = array_fill_keys(array_map('intval', $pageEntries->column('ID')), true);
        foreach ($allEntries as $entry) {
            if (in_array((int)$entry->CategoryID, $closedCategoryIDs, true)) {
                continue;
            }
            $openCount++;
            if (isset($pageEntryIDs[(int)$entry->ID])) {
                $pageOpenCount++;
            }
        }

        $activityCounts = MashaFeedlyEntryRead::unreadActivityCounts($member);
        return $this->respond([
            'success' => true,
            'mode' => $mode,
            'pageCount' => (int)$pageEntries->count(),
            'totalCount' => (int)$allEntries->count(),
            'mineCount' => (int)$mineEntries->count(),
            'openCount' => $openCount,
            'feedbackCount' => $feedbackCount,
            'unreadCount' => $activityCounts['entries'],
            'unreadCommentCount' => $activityCounts['comments'],
            'pageOpenCount' => $pageOpenCount,
            'categories' => $categories,
            'priorities' => array_map(static fn(MashaFeedlyPriority $priority): array => [
                'id' => (int)$priority->ID,
                'title' => (string)$priority->Title,
                'description' => (string)$priority->Description,
                'sort' => (int)$priority->Sort,
                'color' => (string)$priority->Color,
                'iconType' => (string)$priority->IconType,
            ], MashaFeedlyPriority::get()->sort('Sort ASC, Title ASC')->toArray()),
            'entries' => $payload,
        ]);
    }

    /** Legt einen Eintrag samt Seiten- und Elementkontext für ein freigegebenes Mitglied an. */
    public function createEntry(HTTPRequest $request): HTTPResponse
    {
        $member = Security::getCurrentUser();
        if (!MashaFeedlyConfigExtension::canUse($member)) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'CREATE_FORBIDDEN_SIE' : 'CREATE_FORBIDDEN_DU', 'Du darfst keine Masha-Feedly-Einträge erstellen.')], 403);
        }
        if (!$request->isPOST()) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'SEND_POST_SIE' : 'SEND_POST_DU', 'Bitte sende das Formular per POST.')], 405);
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'SESSION_EXPIRED_CREATE_SIE' : 'SESSION_EXPIRED_CREATE_DU', 'Deine Sitzung ist abgelaufen. Lade die Seite neu und versuche es erneut.')], 400);
        }

        $content = trim((string)$request->postVar('Content'));
        if ($content === '') {
            return $this->respond(['success' => false, 'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'CONTENT_REQUIRED_SIE' : 'CONTENT_REQUIRED_DU', 'Bitte beschreibe den Eintrag.')], 400);
        }

        $uploads = $_FILES['Attachments'] ?? [];
        if ($uploadError = MashaFeedlyAttachmentService::validateUploads($uploads)) {
            return $this->respond(['success' => false, 'message' => $uploadError], 400);
        }

        MashaFeedlyCategory::ensureDefaultCategories();
        MashaFeedlyPriority::ensureDefaultPriorities();
        $category = MashaFeedlyCategory::get()->byID((int)$request->postVar('CategoryID'))
            ?? MashaFeedlyCategory::defaultCategory();
        $entry = MashaFeedlyEntry::create();
        $entry->Content = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $entry->CategoryID = (int)$category->ID;
        $postedPriorityID = (int)$request->postVar('PriorityID');
        $priority = $postedPriorityID ? MashaFeedlyPriority::get()->byID($postedPriorityID) : MashaFeedlyPriority::defaultPriority();
        if (!$priority) {
            return $this->respond(['success' => false, 'message' => $this->translate('INVALID_PRIORITY', 'Bitte wählen Sie eine gültige Priorität.')], 400);
        }
        $entry->PriorityID = (int)$priority->ID;
        $entry->PageURL = $this->safePageURL((string)$request->postVar('PageURL'));
        $entry->ElementSelector = mb_substr(trim((string)$request->postVar('ElementSelector')), 0, 512);
        $entry->ElementText = mb_substr(trim((string)$request->postVar('ElementText')), 0, 5000);
        $entry->OperatingSystem = $this->postedText($request, 'OperatingSystem', 255);
        $entry->Browser = $this->postedText($request, 'Browser', 255);
        $entry->UserAgent = $this->postedText($request, 'UserAgent', 512);
        $entry->Resolution = $this->postedText($request, 'Resolution', 50);
        $entry->BrowserWindow = $this->postedText($request, 'BrowserWindow', 50);
        $colorDepth = $request->postVar('ColorDepth');
        $entry->ColorDepth = is_scalar($colorDepth) ? max(0, min(128, (int)$colorDepth)) : 0;
        $date = trim((string)$request->postVar('EntryDate'));
        if ($date !== '') {
            try {
                $entry->EntryDate = (new \DateTimeImmutable($date, new \DateTimeZone('Europe/Berlin')))
                    ->setTimezone(new \DateTimeZone('Europe/Berlin'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return $this->respond(['success' => false, 'message' => $this->translate('INVALID_DATE', 'Bitte prüfe Datum und Uhrzeit.')], 400);
            }
        }

        $entry->write();
        $attachments = MashaFeedlyAttachmentService::attachUploads($uploads, $entry);
        $this->recordAttachmentHistory($entry, $attachments, $member);
        $assignedIDs = MashaFeedlyConfigExtension::normalizeMemberIDs((array)$request->postVar('AssignedMemberIDs'));
        $allowedIDs = MashaFeedlyConfigExtension::memberIDs();
        $entry->AssignedMembers()->setByIDList(array_values(array_intersect($assignedIDs, $allowedIDs)));

        return $this->respond([
            'success' => true,
            'message' => $this->translate(MashaFeedlyConfigExtension::address() === 'sie' ? 'ENTRY_SAVE_SUCCESS_SIE' : 'ENTRY_SAVE_SUCCESS_DU', 'Dein Eintrag wurde gespeichert.'),
            'title' => $entry->Title,
            'entryID' => (int)$entry->ID,
            'attachments' => array_map(static function ($attachment): array {
                $file = $attachment->File();
                return [
                    'name' => (string)$attachment->OriginalName,
                    'mimeType' => (string)$attachment->MimeType,
                    'url' => $file && $file->exists() ? (string)$file->getURL() : '',
                ];
            }, $attachments),
            'history' => MashaFeedlyEntryHistory::dataForEntry($entry),
        ]);
    }

    /** Vereinheitlicht Seitenadressen ohne Query und Fragment für zuverlässige Seitenfilter. */
    private function safePageURL(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || $host === ''
            || isset($parts['user']) || isset($parts['pass'])
        ) {
            return '';
        }
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = '/' . ltrim((string)($parts['path'] ?? ''), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return mb_substr($scheme . '://' . $host . $port . $path, 0, 2048);
    }

    /** Normalisiert eine Beschreibung für einen robusten Ähnlichkeitsvergleich. */
    private function normalizeForSimilarity(string $value): string
    {
        $value = mb_strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** Liefert die gesetzten Verknüpfungen mit sicheren, kompakten Anzeigedaten. */
    private function relationsData(MashaFeedlyEntry $entry): array
    {
        $relations = [];
        foreach ($entry->Relations()->sort('ID ASC') as $relation) {
            $target = $relation->RelatedEntry();
            if (!$target->exists()) {
                continue;
            }
            $relations[] = [
                'id' => (int)$target->ID,
                'title' => (string)$target->Title,
                'type' => (string)$relation->LinkType,
                'categoryTitle' => (string)$target->Category()->Title,
                'direction' => 'outgoing',
            ];
        }
        foreach (MashaFeedlyEntryRelation::get()->filter('RelatedEntryID', (int)$entry->ID)->sort('ID ASC') as $relation) {
            $source = $relation->Entry();
            if (!$source->exists()) {
                continue;
            }
            $incomingType = [
                'blocked_by' => 'blocks',
                'duplicate_of' => 'has_duplicate',
                'related' => 'related_to',
            ][$relation->LinkType] ?? 'related_to';
            $relations[] = [
                'id' => (int)$source->ID,
                'title' => (string)$source->Title,
                'type' => $incomingType,
                'categoryTitle' => (string)$source->Category()->Title,
                'direction' => 'incoming',
            ];
        }
        return $relations;
    }

    /** Schließt offene Duplikate eines abgeschlossenen Haupteintrags und protokolliert jede Statusänderung. */
    private function closeDuplicateEntries(MashaFeedlyEntry $canonicalEntry, MashaFeedlyCategory $doneCategory): int
    {
        $queue = [(int)$canonicalEntry->ID];
        $visited = [(int)$canonicalEntry->ID => true];
        $closedCount = 0;
        while ($queue) {
            $canonicalID = array_shift($queue);
            foreach (MashaFeedlyEntryRelation::get()->filter([
                'RelatedEntryID' => $canonicalID,
                'LinkType' => 'duplicate_of',
            ]) as $relation) {
                $duplicateID = (int)$relation->EntryID;
                if (isset($visited[$duplicateID])) {
                    continue;
                }
                $visited[$duplicateID] = true;
                $queue[] = $duplicateID;
                $duplicate = MashaFeedlyEntry::get()->byID($duplicateID);
                if (!$duplicate || (bool)$duplicate->Category()->IsClosed) {
                    continue;
                }
                $duplicate->CategoryID = (int)$doneCategory->ID;
                $duplicate->write();
                $closedCount++;
            }
        }
        return $closedCount;
    }

    /** Erstellt einen stabilen Verlaufstext zu Verknüpfungen. */
    private function relationsSignature(MashaFeedlyEntry $entry): string
    {
        return implode('; ', array_map(static function (array $relation): string {
            $type = [
                'blocked_by' => 'Blockiert durch',
                'duplicate_of' => 'Duplikat von',
                'related' => 'Thematisch verwandt mit',
            ][$relation['type']] ?? 'Verknüpft mit';
            return $type . ' #' . $relation['id'] . ' ' . $relation['title'];
        }, array_filter($this->relationsData($entry), static fn(array $relation): bool => ($relation['direction'] ?? 'outgoing') === 'outgoing')));
    }

    /** Kürzt optionale Browser-Metadaten sicher auf die jeweilige Spaltenlänge. */
    private function postedText(HTTPRequest $request, string $name, int $maximumLength): string
    {
        $value = $request->postVar($name);
        return is_scalar($value) ? mb_substr(trim((string)$value), 0, $maximumLength) : '';
    }

    /** Bereitet einen Eintrag ohne ungeprüftes HTML für die Widget-Ausgabe auf. */
    private function entryData(MashaFeedlyEntry $entry, Member $currentMember): array
    {
        $assignees = [];
        $assigneeIDs = [];
        foreach ($entry->AssignedMembers() as $assignee) {
            $image = $assignee->MashaFeedlyIconImage();
            $assignees[] = [
                'id' => (int)$assignee->ID,
                'name' => (string)$assignee->getName(),
                'initials' => (string)$assignee->getMashaFeedlyInitials(),
                'color' => (string)$assignee->getMashaFeedlyDisplayColor(),
                'imageURL' => $image instanceof Image && $image->exists() ? (string)$image->getURL() : '',
            ];
            $assigneeIDs[] = (int)$assignee->ID;
        }
        return [
            'id' => (int)$entry->ID,
            'title' => (string)$entry->Title,
            'content' => trim(html_entity_decode(strip_tags((string)$entry->Content), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'entryDate' => (string)$entry->EntryDate,
            'pageURL' => (string)$entry->PageURL,
            'selector' => (string)$entry->ElementSelector,
            'elementText' => (string)$entry->ElementText,
            'loggedAt' => (string)$entry->Created,
            'operatingSystem' => (string)$entry->OperatingSystem,
            'browser' => (string)$entry->Browser,
            'userAgent' => (string)$entry->UserAgent,
            'resolution' => (string)$entry->Resolution,
            'browserWindow' => (string)$entry->BrowserWindow,
            'colorDepth' => (int)$entry->ColorDepth,
            'attachments' => array_map(static function ($attachment): array {
                $file = $attachment->File();
                return [
                    'name' => (string)$attachment->OriginalName,
                    'mimeType' => (string)$attachment->MimeType,
                    'url' => $file && $file->exists() ? (string)$file->getURL() : '',
                ];
            }, $entry->Attachments()->toArray()),
            'categoryID' => (int)$entry->CategoryID,
            'categoryTitle' => (string)$entry->Category()->Title,
            'sort' => (int)$entry->Sort,
            'priorityID' => (int)$entry->PriorityID,
            'priorityTitle' => (string)$entry->Priority()->Title,
            'priorityColor' => (string)$entry->Priority()->Color,
            'priorityIconType' => (string)$entry->Priority()->IconType,
            'isClosed' => (bool)$entry->Category()->IsClosed,
            'assignees' => $assignees,
            'assignedMemberIDs' => $assigneeIDs,
            'comments' => array_map(fn (MashaFeedlyComment $comment): array => $this->commentData($comment, $currentMember), $entry->Comments()->filter('IsApproved', true)->sort('Created ASC')->toArray()),
            'history' => MashaFeedlyEntryHistory::dataForEntry($entry),
            'relations' => $this->relationsData($entry),
        ];
    }

    /** Protokolliert jeden neu hochgeladenen Dateianhang als eigenen Verlaufspunkt. */
    private function recordAttachmentHistory(MashaFeedlyEntry $entry, array $attachments, Member $actor): void
    {
        foreach ($attachments as $attachment) {
            MashaFeedlyEntryHistory::record(
                $entry,
                'attachment',
                '',
                (string)$attachment->OriginalName,
                $actor,
                (int)$attachment->ID
            );
        }
    }

    /** Liefert unveränderliche Namen für eine Liste von Mitgliedskennungen. */
    private function memberNames(array $memberIDs): array
    {
        if (!$memberIDs) {
            return [];
        }
        $names = [];
        foreach (Member::get()->filter('ID', $memberIDs)->sort('Surname ASC, FirstName ASC') as $member) {
            $names[] = (string)$member->getName();
        }
        return $names;
    }

    private function commentData(MashaFeedlyComment $comment, Member $currentMember): array
    {
        $createdAt = strtotime((string)$comment->Created);
        $editedAt = strtotime((string)$comment->LastEdited);
        return [
            'id' => (int)$comment->ID,
            'author' => (string)$comment->AuthorName,
            'text' => (string)$comment->CommentText,
            'created' => (string)$comment->Created,
            'edited' => (bool)$comment->WasEdited || ($createdAt !== false && $editedAt !== false && $editedAt > $createdAt),
            'canManage' => Permission::checkMember($currentMember, 'ADMIN') || (int)$comment->AuthorMemberID === (int)$currentMember->ID,
        ];
    }

    /** Liefert einen Text aus dem Silverstripe-Sprachkatalog des Moduls. */
    private function translate(string $key, string $fallback): string
    {
        return i18n::_t('KW\\MashaFeedly\\Translations.' . $key, $fallback);
    }

    /** Erstellt eine JSON-Antwort mit HTTP-Statuscode. */
    private function respond(array $data, int $status = 200): HTTPResponse
    {
        return $this->getResponse()->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json; charset=utf-8')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
