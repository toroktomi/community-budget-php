# Budapest Community Budget

A participatory-budgeting web app in plain PHP: residents submit project proposals, an admin reviews them, and registered users vote on approved projects.

Built as a university project at ELTE Faculty of Informatics (Web Programming, autumn semester 2025/26). Graded 26 points on a 20-point scale (the extra points came from the optional tasks).

> The user interface is in Hungarian.

## Features

- **Project list**: approved projects are grouped by category (4 fixed categories) and can be filtered with a dropdown. Each project has a detail page.
- **Accounts**: registration and login with sessions. Usernames must be unique and contain no spaces, e-mail format is validated, passwords need at least 8 characters with a lowercase letter, an uppercase letter and a digit. Passwords are hashed with `password_hash()`.
- **Project submission**: title of at least 10 characters, description of at least 150, category from a fixed list, Budapest postal-code check (first digit `1`, district `01`–`23`, last digit `1`–`9`, with `1007` as a special case), optional image URL. ID, owner and submission time are filled in automatically.
- **Review workflow**: new projects start as `pending`. The admin can approve, reject or send a project back for rework with a comment. The owner edits it and resubmits, and this can repeat any number of times.
- **Change history**: each project keeps a timeline of submissions, admin decisions, every rework comment and the old and new value of each edited field.
- **Voting**: votes go to a JSON endpoint (`api-vote.php`) through the Fetch API. Each user gets one vote per project and at most 3 votes per category, and votes can be withdrawn. Voting is open for 2 weeks after a project is approved, and closed projects are marked in the list.
- **Access control**: a project that is not approved can only be opened by its owner or an admin. Other users are redirected to the home page.
- **Admin pages**: a list of pending projects, plus a statistics page showing the most-voted project, the top 3 projects in each category and a project-count table by category and status.

## Tech stack

- PHP with no framework, using sessions and `password_hash` / `password_verify`
- JSON files for storage (`data/*.json`)
- HTML, CSS and vanilla JavaScript (Fetch API)

## How to run

Requirements: PHP 7.4 or newer (PHP 8 recommended) with the `mbstring` extension.

```bash
git clone https://github.com/toroktomi/community-budget-php.git
cd community-budget-php
php -S localhost:8000
```

Then open http://localhost:8000.

Demo accounts are included in `data/users.json`:

| Username   | Password   | Role  |
|------------|------------|-------|
| `admin`    | `admin`    | admin |
| `testuser` | `password` | user  |

The demo projects were approved more than two weeks ago, so voting on them is already closed. To try voting, submit a new project, approve it as `admin`, and vote on it while its two-week window is open.

The app writes to the JSON files in `data/`, so that directory must be writable by the PHP process.

## Project structure

```
index.php            Public project list with category filter and vote buttons
project.php          Project details, history, and admin approve/reject/rework actions
submit-project.php   New project form and validation
edit-project.php     Editing and resubmitting a project sent back for rework
projects-own.php     The logged-in user's own projects in every status
projects-admin.php   Admin list of pending projects
statistics.php       Admin statistics
auth.php             Login, registration, logout
api-vote.php         JSON endpoint for voting and withdrawing votes
config.php           Constants, JSON load/save helpers, auth and voting helpers
data/                JSON storage (users, projects, votes, categories, statuses)
js/voting.js         Fetch-based voting
css/style.css        Styles
```

## What I learned / key challenges

- Building a multi-step state machine (`pending`, `approved`, `rejected`, `rework`) with an audit trail that stores field-level diffs, without a framework or a database.
- Enforcing rules on the server rather than only in the UI: per-category vote limits, the time-limited voting window and redirects for unauthorized users.
- Writing server-side validation for real-world formats such as Budapest postal codes and password complexity, and reporting every error back in the form.
- Separating a small JSON API (`api-vote.php`) from the server-rendered pages and calling it from vanilla JavaScript.

## Author

Török Tamás
