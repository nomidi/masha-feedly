# Using Masha:Feedly

Masha:Feedly is a shared issue and feedback tracker built into your website. People with access can report problems, discuss them, and follow their progress.

The interface uses “report” for issues and change requests. Under **Open profile settings**, choose your **Profile color**, **Thank-you animation**, and form of address. Tour prompts and error messages explain the next action. The preview updates immediately; changes become permanent when you save.

The plus and save buttons stay pink in both appearances. The news button has a light background without a dark rectangle behind the NEW icon.

## Access and setup

Administrators open **Masha:Feedly → Configuration** in the CMS. Settings are grouped on one page into **General**, **Access & appearance**, **Reminders & estimates**, and **Reset data**. Email templates and the footer are in the separate **Email texts** tab. Only the CMS administrator configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can see and use that tab and its actions. Select the people allowed to use the module under **Access & appearance**. Administrators also need explicit access to see the widget. Under **General**, set the form of address, font size, and website default effect category. New installations default to **Playful**. Each allowed member can choose a different category in their profile. The categories for initial status, completion, and approval are required; their names can be customised.

Under **Email texts**, the operator can edit introductions for new reports, updates, comments, due-date reminders, and estimate requests, each in Du and Sie versions. The two welcome texts are at the top. A test email button under each text sends the current editor contents to the operator account, including unsaved edits.

Test messages show sample content in the email layout: new reports include the reporter, category, priority, due date, assignees, and description; comments show the report, comment, and author; due-date reminders include the due date and report details; estimate requests show sample effort, cost, and explanation. The update preview illustrates changes to status, priority, due date, and assignee. These are sample values, not real report data.

For normal notifications, each recipient’s personal form of address in their profile selects Du or Sie. The website setting under **Masha:Feedly → Configuration** controls the welcome email. Feedly adds report details and links automatically. Edit the shared footer in this tab.

If `MASHA_FEEDLY_EFFECTS_BASE_URL` and its API key are configured, `dev/build` copies Masha:Effects texts into empty fields only. **Replace with Masha:Effects texts** fetches them again after an explicit click and replaces the saved welcome and notification texts. This button does not import the footer. Masha:Effects provides protected defaults at `GET /__masha-effects/email-defaults`; Feedly calls it server-side with `Authorization: Bearer …`. The response contains `version`, `footer`, `welcome.du`/`welcome.sie`, and the variants under `notifications`. The provider configuration and website-key setup are documented in Masha:Effects.


The CMS view and its menu item also require explicit module access. The only exception is the CMS administrator whose email is configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL`; this account can open the module configuration without being selected.

Accounts without module access also have no Masha:Feedly tab in their own CMS profile.

Newly approved users receive a welcome email. On their first visit, an onboarding tour walks them through the key steps. They can stop the tour at any time and restart it from their profile or the help panel. In the final dialog, choose a profile color first and, when the provider is available, an icon. A preview shows how your profile appears next to reports and comments. Color and icon changes appear immediately in the preview, even before saving. Then choose the thank-you animation for completed reports and, once email delivery has been tested, configure your email notifications. Expand “Available colors” to open the palette; the current color remains visible while collapsed. The welcome dialog explains reporting at the affected spot, assignments, comments and progress as a shared alternative to coordinating by email. Use “End tour” in the welcome dialog, throughout the steps and in the final dialog to leave onboarding; Esc also works during the tour. “Later” leaves “Show onboarding again” enabled and “Onboarding completed” disabled. “End tour” disables the former and enables the latter. The introduction follows the active form of address: the personal choice, or the website default when no personal choice is set. The welcome, steps, prompts, and profile text use the same form.

When selecting assignees, a compact name label appears above the avatar on hover or keyboard focus.

## Reporting an issue

When creating a report, you can optionally add **Steps to reproduce** and describe what you expected and what actually happened. The section starts collapsed and opens automatically when the description contains common error terms such as “error”, “bug” or “does not work”. You can also open it yourself at any time. Saved details remain separate in the report and can be edited later.

On a phone, open Masha:Feedly using the same button at the bottom right as on a computer. The side panel shows only the plus and help buttons. Mobile help explains how to create a report and states that all tools are available only on a computer with a larger screen. The help text scrolls in small windows while the header and close button stay visible. The tour needs a larger screen: **OK** postpones it, while **Cancel tour** ends it permanently. Page markers remain visible and clickable while the widget is open. The current-page count includes only open reports whose saved page area can still be found on the current page. Reports without an area or with an area that cannot be found appear neither as page markers nor in the current-page count. Open reports with missing targets are not presented as resolved.

Detailed answers about Masha:Feedly live in Masha:Helply. Helply provides its own entry point and searchable dialog. Quick help and the end of the onboarding tour can open Helply directly. When a search finds no answer, the question can be sent to Feedly and continued as a report. Feedly still checks access when the report is created.

## Add a screenshot

Choose the optional **Add screenshot** button in the report form. Your browser asks which tab, window, or screen to share. Select the affected website. Masha:Feedly hides its interface during capture; the preview shows the captured page.

Select the area to include by clicking or tapping one corner and then the opposite corner. With a mouse, the frame follows the pointer between clicks. Choose **Apply crop** to attach that area as a PNG when saving the report. **Remove screenshot** discards the capture. No screenshot is attached until you apply the crop.

The current tool crops the image; it cannot black out individual areas inside the crop. Keep sensitive content outside the frame or remove the capture. If your browser does not support screen capture, attach a screenshot you created yourself as a regular attachment.

## Finding and updating entries

The globe button opens open entries across the website. The page button shows open entries on the current page. The News, Feedback, and completed-entry buttons each open their corresponding list. News highlights entries and comments added since your last visit.

Filter the list by category, priority, page, and assignee. Expand **Filters** to save a personal combination and select it again later. Open an entry to view its description, browser and page details, comments, attachments, history, and relationships. Status, priority, and assignee changes take effect when you select **Save changes**.

History records status, priority, and assignment changes, comments, attachments, and relationships with the person and timestamp. When another person completes your entry, it can move to the required approval category so you can review and approve the result.

The report list loads only the data needed for cards, filters, sorting, and page markers. Descriptions, comments, history, attachments, and relationships are fetched when you open a report. If loading fails, the list remains available; open the report again to retry.

Categories, priorities, and assignees load when the widget first opens, including during onboarding or through a direct report link. The edit form waits for these choices so existing assignments appear correctly. If loading fails, open the report again. These choices are available only to members with access.

## Relationships and duplicates

In the expandable **Relationships** section, link entries as duplicates, thematically related, or blocked by. The relationship appears on both entries and is recorded in history. Completing a primary entry also completes its duplicates. Other status changes are not copied automatically.

## Profile and notifications

Whenever a person is added to Masha:Feedly, including when re-added, their email preferences are set to “My reports” with all event types enabled. A welcome email is sent independently of notification preferences. At the end of the tour, you can open quick help or Masha:Helply. You can also restart the tour there.

“All reports” means emails about all accessible reports on the website, including comments and due dates. “My reports” means reports you created or are currently assigned to. The event checkboxes apply to both scopes. New assignments are covered by “New reports and new assignments”. After an assignment is removed, the report remains in “My reports” only if you created it. Creating or changing reports and posting comments never emails the person taking that action. Cost estimates for approval go only to assignees with that notification enabled. The master option disables all notifications and hides these choices; turning it on shows your saved choices again. Welcome emails and explicitly requested test emails remain independent of the scope.

The avatar preview in your profile uses the same icon and color as entries and assignees. When the color is empty, saving assigns and stores a random palette color once. You or the responsible administrator can change it later. Dark backgrounds, emerald and forest green use the light icon; the other light backgrounds use the dark icon. Only the selected icon and its two variants are stored locally in the project, so displaying it does not require repeated requests to the provider.

In the Masha:Feedly section of your profile, set your avatar and color, choose your personal appearance, manage each type of email notification, and restart the onboarding tour. Available effect categories come from the configured Masha:Effects provider. Administrators set the website default under **Masha:Feedly → Configuration**; the local check effect remains available without a provider or during an outage. New installations default to **Playful**. You can separately choose emails for new entries, entry updates, comments within the selected scope, due dates, and cost estimate requests. Your own actions never generate notification emails. News in the widget shows activity regardless of email notification settings. Personal email settings stay locked until the configured Masha:Feedly administrator has successfully sent at least one test email from Configuration; a notice in the profile explains this. Every Masha:Feedly email includes the footer text from **Masha:Feedly → Configuration → Email footer**, an imprint link, and a direct link to your email settings. If the text field is empty, `MASHA_FEEDLY_EMAIL_FOOTER` from the server `.env` is used. This keeps operator details outside the Git repository. Write line breaks as `\n`. `dev/build` copies the value if no footer has been saved yet.

The footer also explains that the message was sent automatically based on your notification settings. Use its link to change those settings at any time.
New report emails name the website and, when available, show who reported the issue, its category, priority, due date, and assigned people. The full description follows; its automatically generated short title is not repeated separately.

Formatted introductions for new reports, updates, comments, due-date reminders, and estimate requests come from the Masha:Effects provider when configured. Each type has Du and Sie variants. Placeholders such as `{title}` and `{date}` are replaced safely. Report details and links remain part of the email. Masha:Feedly uses its built-in welcome or notification text if no provider is available or the corresponding text field is empty. The footer is configured separately in Feedly, or comes from `MASHA_FEEDLY_EMAIL_FOOTER` when the CMS field is empty.

When `MASHA_FEEDLY_EFFECTS_BASE_URL` and `MASHA_FEEDLY_EFFECTS_API_KEY` are set on the server and Masha:Effects returns a valid icon catalogue, you can also choose a profile icon. The icon catalogue is loaded from the provider through Feedly's protected proxy. The selected SVG icon and its light and dark variants are stored locally. They are grouped into people, animals, nature, everyday, hobbies and technology, fruit and vegetables, food and drink, aliens and UFOs, space, spooky, and dinosaurs. Icon color switches between black and white to contrast with your avatar color. The picker stays hidden without provider configuration. If a configured provider is unavailable, a notice is shown; your saved avatar and profile image upload remain available.

The CMS administrator configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can use **Send test email** under **Masha:Feedly → Configuration** to check delivery to the email address on their account. On failure, Configuration also displays the specific cause, with credentials in connection URLs redacted. Full technical details remain in the PHP error log. If a notification fails, the entry is still saved.

Entries can have an optional due date. Use **Postpone reminder until** to pause only the automatic due-date reminder; comment and update emails continue. On the selected date, an entry is considered again if it is still open and due. In Masha:Feedly settings, choose under **Run due-date reminders** whether checks run via cron or on the first website visit of each day. Visitor mode sends no reminders until someone visits the site. The check sends one email on the due date (or after it, if it did not run on time) to approved members with due date notifications enabled and a matching email scope. Changing the due date resets the reminder.

For cron mode, schedule this command to run daily in your Silverstripe project:

```sh
vendor/bin/sake tasks:MashaFeedlyDueDateReminderTask
```

For ALL-INKL HTTP cron jobs, see the `masha-cronjob/` folder in the project root for instructions and example files. If you cannot run a shell cron job, select **On website visits** instead.

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
MASHA_FEEDLY_MITE_ACCOUNT="your-mite-account"
```

The API key belongs to your Mite user and is never exposed in the CMS or browser. After `dev/build?flush=1`, enable the integration under **Masha:Feedly → Mite**, choose this website's default project and one or more **trigger categories**, then save the configuration. Choose the **service** each time you start a timer. Mite is disabled by default. Only the CMS administrator whose email is configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can access the tab, the general Mite timer button in the widget's first section, and timer functions. Other people can still use the board but do not see the Mite button or receive a Mite dialog.

When you move an issue to a selected **trigger category** in the CMS board or change its status in the entry dialog, **Start Mite timer?** opens. It loads available projects and services and shows any currently running timer. **Start timer** creates a Mite time entry with the full issue description as its note, the selected project and service, and starts its tracker. The default project is preselected; choose a project and service for each start. Use **Mite timer** in the first section of the page widget to start general time tracking without a Feedly entry or stop a running timer. General entries use the note “Masha:Feedly – general time tracking” in Mite.

If the running timer changes while the dialog is open, use **Reload** to review its updated state. API errors keep the issue in the selected category and show a message in the dialog. Feedly does not store Mite time entries or associations; time data remains in Mite. Use **Stop timer** in the widget dialog to stop a running timer. Reordering within the same category does not open a dialog. Disabling Mite hides timer dialogs and blocks starts and stops from dialogs that are already open. Resetting Feedly removes trigger categories and disables Mite.

In the CMS, open **Masha:Feedly → Entries → Change reporter**. Search by entry ID or title and save the displayed reporter directly in the row.

## Installation and tests

See the [project README](../../../README.md) for installation and PHP and JavaScript test commands. Browser-based end-to-end tests are documented in [`../../tests/e2e/README.md`](../../tests/e2e/README.md).

## Completion effects

Effect delivery is optional. Without a reachable provider, Feedly plays a subtle local checkmark after a confirmed completion. This also applies when the catalogue is empty or an effect fails to load.

For centrally managed animations, install the standalone Silverstripe **Masha:Effects** module on the same or another website. It manages categories, effects, files, and seasonal rules. Files stay private on the provider server. Feedly contains only the loader and fetches matching effects through a protected server proxy. Provider failure never blocks saving.

Set `MASHA_FEEDLY_EFFECTS_BASE_URL` in the Feedly website's server `.env` to the provider base address, for example `https://effects.example.org`; Feedly appends `/__masha-effects/manifest`. Without a fully configured provider, Feedly does not request an effect catalogue and uses the local checkmark effect. This also applies to CMS previews. Provider outages and empty catalogues do not block saving.

For an external provider, set `MASHA_FEEDLY_EFFECTS_API_KEY` in the Feedly website's server `.env`. Generate the key in the provider CMS and keep it server-side. Browsers receive only local proxy URLs; the proxy checks login and Feedly access.

### External effect provider interface

The provider must support HTTPS and implement these two GET endpoints. Both require `Authorization: Bearer <key>`:

- `GET /__masha-effects/manifest` returns JSON with `Content-Type: application/json`.
- `GET /__masha-effects/file/{ID}/{SHA256}/{Type}` returns a versioned JS, CSS, or image file.

Example manifest:

```json
{
  "version": 2,
  "maxAge": 300,
  "categories": [{"id": "serious", "name": "Serious"}],
  "effects": [
    {
      "id": "myEffect",
      "name": "My effect",
      "text": "Done!",
      "detail": "The entry is completed.",
      "categories": ["serious"],
      "weight": 1,
      "files": {
        "js": "https://effects.example.org/__masha-effects/file/42/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef/js",
        "css": "https://effects.example.org/__masha-effects/file/42/abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789/css",
        "image": "https://effects.example.org/__masha-effects/file/42/abcdefabcdef0123456789abcdef0123456789abcdef0123456789abcdef01/image"
      }
    }
  ]
}
```

`maxAge` is the catalogue lifetime in seconds; Feedly clamps it to at most 300 seconds. `categories` contains active categories with a stable `id` and display `name`; IDs must match `/^[a-z][a-z0-9_-]{0,79}$/`. Effects need at least one category and may belong to multiple categories. `effects` may be empty and contain at most 1000 entries. Each item needs `id`, `name`, `categories`, `weight`, and `files.js`. `id` must match `/^[A-Za-z][A-Za-z0-9_-]{0,79}$/`; `weight` an integer from 1 to 100. `files.css` and `files.image` are optional.

Each file URL must be absolute, use the provider's same HTTPS host and base path, and match `/__masha-effects/file/{positive ID}/{64 lowercase hexadecimal characters}/{js|css|image}`. `{SHA256}` is the SHA-256 hash of the exact file bytes. Feedly does not follow redirects and verifies the URL, file hash, and MIME type. Allowed MIME types are `text/javascript` or `application/javascript` for JavaScript, `text/css` for stylesheets, and `image/svg+xml`, `image/png`, `image/jpeg`, `image/webp`, or `image/gif` for images. The provider should return `403` without a valid key. `Cache-Control: private, no-store`, `Vary: Authorization`, and `X-Content-Type-Options: nosniff` are recommended for protected responses.


In your profile and the final onboarding step, enable **Play animations without sound**. All effects remain available and play without music or other sounds, including previews in the Masha:Feedly configuration. Save to keep your preference. Reload configuration windows that were already open.

The effect provider must also use the current Masha:Effects version: its JavaScript files must honour the `muted` option. Older provider files may still play sound even when the profile preference is correct.


When switching between mobile and desktop layouts, Feedly closes its windows. Active onboarding returns to the welcome screen in the appropriate layout. Form input is preserved. Resizing within the same layout does not close windows.

### Confirm completion

When another person selects “Done”, the report remains open as “Feedback”. A prominent notice explains the next action. The original creator or designated reporter reviews the result and confirms completion with “Done” and “Save changes”. Assignment alone does not grant final approval. A comment is optional. This rule also applies when moving or saving reports in the CMS.

Categories, priorities and assignees are fetched when the widget is first opened; the page initially renders empty selection fields. The protected endpoint only returns these values to members with access.

Helply links are shown only when Helply provides the authorised Feedly help collection on the current page. Without Helply, its buttons and hints remain hidden.

## Automatically captured technical errors

The host project includes ALL-INKL KAS setup instructions in `masha-cronjob/README.en.md` and a protected HTTP cron example in `masha-cronjob/all-inkl/run-masha-feedly-errors.php.example`. The guide covers a shared private website/CLI spool, the email filter and how KAS and Feedly intervals interact.

The configured operator can enable capture under **Masha:Feedly → Configuration → Technical errors**. The account specified by `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` must exist and have administrator permissions. Every technical report is always assigned to this account. Only the operator and additionally selected people who also have general Feedly access can see technical reports.

Errors are grouped using a sanitised message and their origin. Variable numbers and UUIDs are normalised for matching. Details show the total count and first/last occurrence in UTC. New errors use existing new-report notifications. Resolved errors that recur reopen and use update notifications. Personal email preferences and the “My reports” scope still apply. Repetitions of open errors only increase the count, without comments or update emails.

**Processing:** Choose cron or website visits and an interval of 1–1440 minutes. Run this command every minute from the project directory; Feedly checks its configured interval:

```sh
php vendor/bin/sake tasks:MashaFeedlyErrorTask
```

Visitor mode checks on Silverstripe content-page requests. Without these visits, or with fully cached pages, processing is delayed. Concurrent runs are prevented. Each run imports at most 100 error groups; remaining groups follow in the next interval.

**Daily summary:** Optionally enable it and choose an HH:MM time in Europe/Berlin. The message lists errors occurring in the reporting period, their total counts and links. Personal email master switch, report scope and technical access apply. Individual event checkboxes continue to control immediate notifications. Empty summaries are not sent. Without cron or a website visit, delivery is delayed. The first summary covers at most the preceding 24 hours. Interrupted or failed summary delivery is not retried automatically on that day.

**Server setup:** The module registers its logging handler automatically; capture starts only after activation. Existing `NativeMailerHandler` registrations remain active: remove them to stop individual technical error emails. Keep an independent PHP/server error log. After installation or upgrading, run `dev/build flush=1` and save the configuration.

The private spool defaults to a project-specific directory under `TEMP_PATH`. Webserver and cron must use the same temp path and have access to the same files. Different server users or multiple servers require an appropriate shared private spool. Configure an absolute path via YAML if needed:

```yaml
KW\MashaFeedly\Logging\MashaFeedlyErrorHandler:
  spool_directory: '/srv/private/feedly-errors'
```

The directory must be outside the webroot. Directories and files use 0700 and 0600 permissions. Capture includes events at the configured minimum level (Error, Critical, Alert or Emergency) forwarded to the Silverstripe logger. Errors before framework startup may only reach the independent server log. Capture does not need a database. Failed imports remain queued for retry; disabling capture does not delete pending batches.

Full request data, context values and trace arguments are omitted. Common credential patterns and URL query strings are removed. Free-text error messages may still contain sensitive information; limit technical access accordingly. Events during importing or email delivery are not captured again to avoid feedback loops. Resetting the module removes imported reports; pending batches may recreate them on the next run.

Feedly configuration uses expandable sections. General settings and technical errors appear first, followed by separate access, profile colour, effect preview, reminders/estimates and reset sections. The header shows the saved email and error capture status. Changes take effect only after **Save configuration**.

**Technical error location:** When an error occurs on a content page, capture records its local path without query strings or fragments. Without a content page, the report uses the website’s `/admin` path. A subsequently known content page replaces a previous admin fallback. Identical errors on further pages remain one report with the first known content location. CMS board titles are clickable for local paths and older technical reports without a stored location. Admin links also carry the report ID in the URL fragment so it survives CMS redirects. New records default to `/admin`; explicitly empty or unsafe page addresses prevent saving. `dev/build` fills empty existing locations with the admin path without sending update emails.

The original FAQ button with its book icon sits directly below the question mark and opens the Feedly collection in Helply. It appears only when the “feedly” collection is available to the current account. With the current Masha:Helply installed, Feedly also makes the collection available in CMS areas such as the profile, report board and configuration. Helply still checks CMS access and global/collection group permissions, and Feedly access is also required. Without permission or without Helply, the button and hint remain hidden.

### Markers in CMS tabs and image dialogs

Feedly resolves saved areas again after loading and whenever the CMS form changes. Existing tabs containing an opened report’s target are revealed. Visible targets receive a bubble; hidden or removed targets are never marked at an unrelated location. Open reports with missing targets do not imply that all reports are resolved.

Older reports created inside an image dialog contain the field selector but not the image identity. Feedly cannot safely restore a specific image dialog from that information. The report explains this: close it and open the relevant image. The marker appears automatically once the field becomes visible. Reports whose fields were removed remain available in the list.

Use **Show marked area** to close the report overlays and scroll to its visible bubble. The opened report’s marker remains highlighted.

The report editor joins the list without an extra gap, including in the success state. All three panels use the same responsive widths; on phones the editor remains full screen.
