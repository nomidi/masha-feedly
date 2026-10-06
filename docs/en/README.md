# Using Masha:Feedly

Masha:Feedly is a shared issue and feedback tracker built into your website. People with access can report problems, discuss them, and follow their progress.

## Access and setup

Administrators open **Masha:Feedly → Configuration** in the CMS and select who may use the module. Administrators only see the widget when explicitly selected as well. The same screen controls the form of address, font size, website default appearance, categories, and priorities. The default is **Playful** on new installations. Each approved user can choose a different theme in their own profile. The categories for the initial status, completion, and approval are required; their labels can be changed.

Newly approved users receive a welcome email. On their first visit, an onboarding tour walks them through the key steps. They can stop the tour at any time and restart it from their profile or the help panel.

## Reporting an issue

1. Open Masha:Feedly using the icon in the lower-right corner.
2. Select **+**, then click the affected area of the page.
3. Enter a title and description. Add a status, priority, assignees, or files if needed.
4. Save the entry. A numbered marker points to the clicked spot on the selected element and stays relative to that element when the window size changes. While the widget is open, the marker also follows the element during animations and subsequent layout changes. New entries save a complete selector path to distinguish sections with identical layouts. For older ambiguous selector paths, the saved element text identifies the target; if it remains ambiguous, no marker is shown.

Comments support links. Images appear inline; PDF and ZIP files are shown as protected download links. Only people with Masha:Feedly access can view entries and attachments.

## Finding and updating entries

The globe button opens open entries across the website. The page button shows open entries on the current page. The News, Feedback, and completed-entry buttons each open their corresponding list. News highlights entries and comments added since your last visit.

Filter the list by category, priority, page, and assignee. Expand **Filters** to save a personal combination and select it again later. Open an entry to view its description, browser and page details, comments, attachments, history, and relationships. Status, priority, and assignee changes take effect when you select **Save changes**.

History records status, priority, and assignment changes, comments, attachments, and relationships with the person and timestamp. When another person completes your entry, it can move to the required approval category so you can review and approve the result.

## Relationships and duplicates

In the expandable **Relationships** section, link entries as duplicates, thematically related, or blocked by. The relationship appears on both entries and is recorded in history. Completing a primary entry also completes its duplicates. Other status changes are not copied automatically.

## Profile and notifications

In the Masha:Feedly section of your profile, set your avatar and color, choose your personal appearance, manage each type of email notification, and restart the onboarding tour. Choose **Website default**, **Playful**, or **Serious**. Administrators set the website default under **Masha:Feedly → Configuration**; new installations use **Playful** by default. You can separately choose emails for new entries, entry updates, comments on entries assigned to you or created by you, due dates, and cost estimate requests. Emails about your own entries and changes are optional and off by default. News in the widget shows activity regardless of email notification settings.

The CMS administrator configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can use **Send test email** under **Masha:Feedly → Configuration** to check delivery to the email address on their account. If a notification fails, the entry is still saved and the error is written to the PHP error log.

Entries can have an optional due date. In Masha:Feedly settings, choose under **Run due-date reminders** whether checks run via cron or on the first website visit of each day. Visitor mode sends no reminders until someone visits the site. The check sends one email on the due date (or after it, if it did not run on time) to approved assignees and the entry creator. Changing the date resets the reminder.

For cron mode, schedule this command to run daily in your Silverstripe project:

```sh
vendor/bin/sake dev/tasks/MashaFeedlyDueDateReminderTask
```

## Managing entries in the CMS

The Masha:Feedly board groups entries by category. Users with access can edit entries and reorder them with drag and drop. Categories, priorities, and general widget settings are also managed in the CMS.

The specifically configured superadmin account also sees **Delete all Masha:Feedly data** under **Masha:Feedly → Configuration**. It removes entries and their comments, reactions, attachments, relationships, read markers, and history, then recreates the standard categories including optional estimate categories. Users, profiles, access rights, and configuration are kept. Type `RESET` to confirm.

When manually recreating older entries, a designated operator can change the displayed reporter. Add only that operator account's email address to your project's `app/_config.php`:

```php
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Config\Config;

Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [
    'your-account@example.org',
]);
```

The account must also be a Silverstripe CMS administrator. Customer CMS administrators without the configured email do not receive access to the selector. The actual creator remains unchanged, and reporter changes are recorded in history.

Configure your personal Mite connection exclusively in `.env`:

```dotenv
MASHA_FEEDLY_MITE_API_KEY="your-personal-api-key"
MASHA_FEEDLY_MITE_ACCOUNT="kooperative-web"
```

The API key belongs to your Mite user and is never exposed in the CMS or browser. After `dev/build?flush=1`, enable the integration under **Masha:Feedly → Mite**, choose this website's default project and one or more **trigger categories**, then save the configuration. Choose the **service** each time you start a timer. Mite is disabled by default. Only the CMS administrator whose email is configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can access the tab, the general Mite timer button in the widget's first section, and timer functions. Other people can still use the board but do not see the Mite button or receive a Mite dialog.

When you move an issue to a selected **trigger category** in the CMS board or change its status in the entry dialog, **Start Mite timer?** opens. It loads available projects and services and shows any currently running timer. **Start timer** creates a Mite time entry with the full issue description as its note, the selected project and service, and starts its tracker. The default project is preselected; choose a project and service for each start. Use **Mite timer** in the first section of the page widget to start general time tracking without a Feedly entry or stop a running timer. General entries use the note “Masha:Feedly – general time tracking” in Mite.

If the running timer changes while the dialog is open, use **Reload** to review its updated state. API errors keep the issue in the selected category and show a message in the dialog. Feedly does not store Mite time entries or associations; time data remains in Mite. Use **Stop timer** in the widget dialog to stop a running timer. Reordering within the same category does not open a dialog. Disabling Mite hides timer dialogs and blocks starts and stops from dialogs that are already open. Resetting Feedly removes trigger categories and disables Mite.

In the CMS, open **Masha:Feedly → Entries → Change reporter**. Search by entry ID or title and save the displayed reporter directly in the row.

## Installation and tests

See the [project README](../../../README.md) for installation and PHP and JavaScript test commands. Browser-based end-to-end tests are documented in [`../../tests/e2e/README.md`](../../tests/e2e/README.md).
