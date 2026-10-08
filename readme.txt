=== JobCore ===
Contributors: xolius
Tags: job board, jobs, careers, job listings, recruitment
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete, good-looking job board: search and filters, employer pages, applications with CV upload, job alerts and a front-end account.

== Description ==

JobCore is a full job board in one free plugin — not a starter that needs five paid extensions before it is useful. Install it, run the two-minute setup wizard and your board is live.

= For job seekers =

* Fast board with keyword, location and remote search, categories, job types and filters
* Clean job pages with structured data for Google Jobs
* Apply with an e-mail and CV upload, or on the employer's own site
* Job alerts by e-mail for the jobs they want
* Bookmarks and a front-end account with all their applications

= For employers =

* Company pages with logo, about text, links and all open jobs
* Post and manage job ads from the front-end account — no wp-admin needed
* Applications inbox with shortlist / reject, and the CV and letter of every applicant
* Optional review before a new job ad goes live

= For you =

* Setup wizard: pages, look, contact details and sample jobs in two minutes
* Board inside your theme, or as a standalone portal with its own header and footer
* Accent colour, logo, header style and hero image under Jobs → Settings
* Import / export of jobs and employers
* Works with any theme; extra polish with the News. theme
* Social login buttons on the account screens when Nextend Social Login is installed
* Translation-ready

= Paid add-ons =

JobCore never locks features. If you want to earn from your board, paid add-ons are available under Jobs → Add-ons:

* **Paid Listings** — sell job packages with WooCommerce
* **Promoted Ads** — paid ad slots on the board
* **Field Editor** — your own fields on the job and application forms
* **Looks** — premium board designs
* **Job Widget** — show your jobs on other websites

== Installation ==

1. Install and activate JobCore from Plugins → Add New.
2. Follow the setup wizard (or open Jobs → Settings later).
3. Your board lives on the Jobs page. You can also place `[wpjc_jobs]` on any page.

== Frequently Asked Questions ==

= Do I need WP Job Manager or another plugin? =

No. JobCore is standalone. WooCommerce is only needed for the Paid Listings add-on.

= Does it work with my theme? =

Yes. The board can use your theme's header and footer, or run as a standalone portal with its own.

= Which shortcode shows the board? =

`[wpjc_jobs]`. It accepts `per_page`, `employer`, `category`, `type` and `title`.

= Is there social login? =

Install the free Nextend Social Login plugin and its buttons appear on the JobCore sign-in screens.

= Is anything locked without a licence key? =

No. A licence key is only for automatic updates of the paid add-ons.

= Where do the sample job photos come from? =

The photos used by the optional sample jobs (assets/demo) were created by Xolius for JobCore with an AI image tool. They are released under the GPLv2 or later, like the rest of the plugin.

== External services ==

JobCore does not contact any external service on its own. Two optional features do, and only after an administrator turns them on:

**Xolius licence server (paid add-ons)**
When an administrator registers a licence key under Jobs → Add-ons, the plugin connects to the Xolius licence server (https://xolius.com/licence/) to activate the key. It sends the licence key, the site address and an anonymous site ID (a hash). After that, JobCore checks the key once a week and when the Add-ons screen is opened, and installed paid add-ons look for their own updates; these requests send the key and the installed add-on versions. No information about your visitors, users or content is ever sent. Removing the key stops all requests.
Terms: https://xolius.com/terms/ — Privacy policy: https://xolius.com/privacy-policy/

**Google Indexing API (optional)**
When an administrator turns on "Tell Google when a job goes live, changes or closes" under Jobs → Settings and pastes their own Google Cloud service account key, JobCore tells Google when a job goes live, changes or closes. It signs in to Google with that key (https://oauth2.googleapis.com/token) and sends the job's web address and whether it was updated or removed (https://indexing.googleapis.com/v3/url). Nothing else is sent. Turning the setting off or removing the key stops all requests.
Google APIs Terms of Service: https://developers.google.com/terms — Google Privacy Policy: https://policies.google.com/privacy

The "Get it" and "Learn more" buttons on the Add-ons screen are ordinary links to the Xolius shop (https://xolius.com/); nothing is sent until you click them.

== Screenshots ==

1. The job board with search and filters.
2. A job page with apply form.
3. An employer page.
4. The front-end account: applications, alerts and job ads.
5. The setup wizard.
6. Jobs → Settings.

== Changelog ==

= 1.0.3 =
* Job board on phones: the contact row and its buttons wrap instead of running off the screen.

= 1.0.2 =
* First release on WordPress.org: board, search and filters, employer pages, applications with CV upload, job alerts, bookmarks, front-end account, setup wizard, import / export, standalone portal look and Nextend Social Login support.
* Cart link in the jobs header when WooCommerce has items in the cart (paid listings).
* "Create resume" button when the Resumes add-on is active.
* Live search results stay on top of the page.

== Upgrade Notice ==

= 1.0.3 =
Small layout fix for phones.

= 1.0.2 =
First release on WordPress.org.
