# Bill Splitter

A simple bill splitting tool. Add people, add items, and it calculates each person's total including service charge and GST.

## Features

- Add/remove people and items dynamically
- Supports math expressions in amount fields (e.g. `10+5*2`)
- Customizable service charge and GST rates
- Save bills and share via link
- View-only mode for shared links
- OG link preview with person names and totals when sharing on chat apps
- Print-friendly layout
- Mobile responsive

## Tech

- PHP (server-side rendering for OG meta tags)
- Vanilla JS (no frameworks)
- CSS (no libraries)
- File-based storage (JSON files in `bills/`)

## Setup

1. Clone the repo to your web server
2. Make sure `bills/` directory is writable (`chmod 777 bills/`)
3. That's it

## File Structure

```
index.php       - Main page with dynamic OG tags
app.js          - Client-side logic
styles.css      - Styles
api.php         - REST API for saving/loading bills
og-image.php    - Dynamic OG image generator for link previews
bills/          - Stored bill data (JSON)
```
