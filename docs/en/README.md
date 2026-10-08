# Using Masha:Feedly

Masha:Feedly is a shared issue and feedback tracker built into your website. People with access can report problems, discuss them, and follow their progress.

The interface uses “report” for issues and change requests. Under **Open profile settings**, choose your **Profile color**, **Thank-you animation**, and form of address. Tour prompts and error messages explain the next action. The preview updates immediately; changes become permanent when you save.

The plus and save buttons stay pink in both appearances. The news button has a light background without a dark rectangle behind the NEW icon.

## Access and setup

Administrators open **Masha:Feedly → Configuration** in the CMS. Settings are grouped on one page into **General**, **Access & appearance**, **Reminders & estimates**, **Reset data**, and **Email footer**. Operator settings are visible only to the explicitly configured operator account; the email footer section starts collapsed. Only that account also sees the test email and reset actions; regular CMS administrators do not. Select the people allowed to use the module under **Access & appearance**. Administrators also need explicit access to see the widget. Under **General**, set the form of address, font size, and website default effect category. New installations default to **Playful**. Each allowed member can choose a different category in their profile. The categories for initial status, completion, and approval are required; their names can be customised.

The CMS view and its menu item also require explicit module access. The only exception is the CMS administrator whose email is configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL`; this account can open the module configuration without being selected.

Accounts without module access also have no Masha:Feedly tab in their own CMS profile.

Newly approved users receive a welcome email. On their first visit, an onboarding tour walks them through the key steps. They can stop the tour at any time and restart it from their profile or the help panel. In the final dialog, choose a profile color first and, when the provider is available, an icon. A preview shows how your profile appears next to reports and comments. Color and icon changes appear immediately in the preview, even before saving. Then choose the thank-you animation for completed reports and, once email delivery has been tested, configure your email notifications. Expand “Available colors” to open the palette; the current color remains visible while collapsed. The welcome dialog explains reporting at the affected spot, assignments, comments and progress as a shared alternative to coordinating by email. Use “End tour” in the welcome dialog, throughout the steps and in the final dialog to leave onboarding; Esc also works during the tour. “Later” leaves “Show onboarding again” enabled and “Onboarding completed” disabled. “End tour” disables the former and enables the latter. The introduction follows the active form of address: the personal choice, or the website default when no personal choice is set. The welcome, steps, prompts, and profile text use the same form.

When selecting assignees, a compact name label appears above the avatar on hover or keyboard focus.

## Reporting an issue

When creating a report, you can optionally add **Steps to reproduce** and describe what you expected and what actually happened. The section starts collapsed and opens automatically when the description contains common error terms such as “error”, “bug” or “does not work”. You can also open it yourself at any time. Saved details remain separate in the report and can be edited later.

On a phone, open Masha:Feedly using the same button at the bottom right as on a computer. The side panel shows only the plus and help buttons. Mobile help explains how to create a report and states that all tools are available only on a computer with a larger screen. The help text scrolls in small windows while the header and close button stay visible. The tour needs a larger screen: **OK** postpones it, while **Cancel tour** ends it permanently. Page markers remain visible and clickable while the widget is open.

## Add a screenshot

Choose the optional **Add screenshot** button in the report form. Your browser asks which tab, window, or screen to share. Select the affected website. Masha:Feedly hides its interface during capture; the preview shows the captured page.

Select the area to include by clicking or tapping one corner and then the opposite corner. With a mouse, the frame follows the pointer between clicks. Choose **Apply crop** to attach that area as a PNG when saving the report. **Remove screenshot** discards the capture. No screenshot is attached until you apply the crop.

The current tool crops the image; it cannot black out individual areas inside the crop. Keep sensitive content outside the frame or remove the capture. If your browser does not support screen capture, attach a screenshot you created yourself as a regular attachment.

## Finding and updating entries

The globe button opens open entries across the website. The page button shows open entries on the current page. The News, Feedback, and completed-entry buttons each open their corresponding list. News highlights entries and comments added since your last visit.

Filter the list by category, priority, page, and assignee. Expand **Filters** to save a personal combination and select it again later. Open an entry to view its description, browser and page details, comments, attachments, history, and relationships. Status, priority, and assignee changes take effect when you select **Save changes**.

History records status, priority, and assignment changes, comments, attachments, and relationships with the person and timestamp. When another person completes your entry, it can move to the required approval category so you can review and approve the result.

## Relationships and duplicates

In the expandable **Relationships** section, link entries as duplicates, thematically related, or blocked by. The relationship appears on both entries and is recorded in history. Completing a primary entry also completes its duplicates. Other status changes are not copied automatically.

## Profile and notifications

The avatar preview in your profile uses the same icon and color as entries and assignees. When the color is empty, saving assigns and stores a random palette color once. You or the responsible administrator can change it later. Dark backgrounds, emerald and forest green use the light icon; the other light backgrounds use the dark icon. Only the selected icon and its two variants are stored locally in the project, so displaying it does not require repeated requests to the provider.

In the Masha:Feedly section of your profile, set your avatar and color, choose your personal appearance, manage each type of email notification, and restart the onboarding tour. Available effect categories come from the configured Masha:Effects provider. Administrators set the website default under **Masha:Feedly → Configuration**; the local check effect remains available without a provider or during an outage. New installations default to **Playful**. You can separately choose emails for new entries, entry updates, comments on entries assigned to you or created by you, due dates, and cost estimate requests. Emails about your own entries and changes are optional and off by default. News in the widget shows activity regardless of email notification settings. Personal email settings stay locked until the configured Masha:Feedly administrator has successfully sent at least one test email from Configuration; a notice in the profile explains this. Every Masha:Feedly email includes the footer text from **Masha:Feedly → Configuration → Email footer**, an imprint link, and a direct link to your email settings. If the text field is empty, `MASHA_FEEDLY_EMAIL_FOOTER` from the server `.env` is used. This keeps operator details outside the Git repository. Write line breaks as `\n`. `dev/build` copies the value if no footer has been saved yet.

The footer also explains that the message was sent automatically based on your notification settings. Use its link to change those settings at any time.
New report emails name the website and, when available, show who reported the issue, its category, priority, due date, and assigned people. The full description follows; its automatically generated short title is not repeated separately.

When `MASHA_FEEDLY_EFFECTS_BASE_URL` and `MASHA_FEEDLY_EFFECTS_API_KEY` are set on the server and Masha:Effects returns a valid icon catalogue, you can also choose a profile icon. The icon catalogue is loaded from the provider through Feedly's protected proxy. The selected SVG icon and its light and dark variants are stored locally. They are grouped into people, animals, nature, everyday, hobbies and technology, fruit and vegetables, food and drink, aliens and UFOs, space, spooky, and dinosaurs. Icon color switches between black and white to contrast with your avatar color. The picker stays hidden without provider configuration. If a configured provider is unavailable, a notice is shown; your saved avatar and profile image upload remain available.

The CMS administrator configured through `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` can use **Send test email** under **Masha:Feedly → Configuration** to check delivery to the email address on their account. On failure, Configuration also displays the specific cause, with credentials in connection URLs redacted. Full technical details remain in the PHP error log. If a notification fails, the entry is still saved.

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

Set `MASHA_FEEDLY_EFFECTS_BASE_URL` in the Feedly website's server `.env` to the provider base address, for example `https://effects.example.org`; Feedly appends `/__masha-effects/manifest`. Without this setting, Feedly looks on the same website, where Masha:Effects must be installed and configured. Otherwise, the local fallback plays. Provider outages and empty catalogues do not block saving.

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
