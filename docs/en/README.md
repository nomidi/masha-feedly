# Using Masha:Feedly

Masha:Feedly is a shared issue and feedback tracker built into your website. People with access can report problems, discuss them, and follow their progress.

## Access and setup

Administrators open **Masha:Feedly → Configuration** in the CMS and select who may use the module. Administrators always retain access. The same screen controls the form of address, font size, appearance, categories, and priorities. The categories for the initial status, completion, and approval are required; their labels can be changed.

Newly approved users receive a welcome email. On their first visit, an onboarding tour walks them through the key steps. They can stop the tour at any time and restart it from their profile or the help panel.

## Reporting an issue

1. Open Masha:Feedly using the icon in the lower-right corner.
2. Select **+**, then click the affected area of the page.
3. Enter a title and description. Add a status, priority, assignees, or files if needed.
4. Save the entry. A numbered marker points to its location on the page.

Comments support links. Images appear inline; PDF and ZIP files are shown as protected download links. Only people with Masha:Feedly access can view entries and attachments.

## Finding and updating entries

The globe button opens open entries across the website. The page button shows open entries on the current page. The News, Feedback, and completed-entry buttons each open their corresponding list. News highlights entries and comments added since your last visit.

Filter the list by category, priority, page, and assignee. Expand **Filters** to save a personal combination and select it again later. Open an entry to view its description, browser and page details, comments, attachments, history, and relationships. Status, priority, and assignee changes take effect when you select **Save changes**.

History records status, priority, and assignment changes, comments, attachments, and relationships with the person and timestamp. When another person completes your entry, it can move to the required approval category so you can review and approve the result.

## Relationships and duplicates

In the expandable **Relationships** section, link entries as duplicates, thematically related, or blocked by. The relationship appears on both entries and is recorded in history. Completing a primary entry also completes its duplicates. Other status changes are not copied automatically.

## Profile and notifications

In the Masha:Feedly section of your profile, set your avatar and color, manage comment and due-date email reminders, and enable the onboarding tour again. News in the widget shows activity regardless of email notification settings.

Entries can have an optional due date. In Masha:Feedly settings, choose under **Run due-date reminders** whether checks run via cron or on the first website visit of each day. Visitor mode sends no reminders until someone visits the site. The check sends one email on the due date (or after it, if it did not run on time) to approved assignees and the entry creator. Changing the date resets the reminder.

For cron mode, schedule this command to run daily in your Silverstripe project:

```sh
vendor/bin/sake dev/tasks/MashaFeedlyDueDateReminderTask
```

## Managing entries in the CMS

The Masha:Feedly board groups entries by category. Users with access can edit entries and reorder them with drag and drop. Categories, priorities, and general widget settings are also managed in the CMS.

When manually recreating older entries, a designated operator can change the displayed reporter. Add only that operator account's email address to your project's `app/_config.php`:

```php
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Config\Config;

Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [
    'your-account@example.org',
]);
```

The account must also be a Silverstripe CMS administrator. Customer CMS administrators without the configured email do not receive access to the selector. The actual creator remains unchanged, and reporter changes are recorded in history.

In the CMS, open **Masha:Feedly → Entries → Change reporter**. Search by entry ID or title and save the displayed reporter directly in the row.

## Installation and tests

See the [project README](../../../README.md) for installation and PHP and JavaScript test commands. Browser-based end-to-end tests are documented in [`../../tests/e2e/README.md`](../../tests/e2e/README.md).
