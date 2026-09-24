<<<<<<< HEAD
# Scuppet Media — Website

A premium, frontend-only website for **Scuppet Media**, built with HTML5, CSS3, vanilla JavaScript, Bootstrap 5, and Bootstrap Icons. No backend, no build step — open and go.

## Structure

```
scuppet-media/
├── index.html            Home
├── about.html             About
├── services.html          Services (detailed, 4 sections)
├── client-stories.html    Client Stories (editorial testimonials)
├── contact.html            Contact (validated form, no backend)
├── css/
│   └── style.css          Design system + all page styles
├── js/
│   └── main.js             Navbar state, reveal animations, testimonial
│                            slider, form validation, cursor interaction
├── images/
│   └── hero.jpg            Home hero background (supplied artwork)
└── README.md
```

## Running it

No build tools needed. Either:
- Open `index.html` directly in a browser, or
- Serve the folder locally, e.g. `python3 -m http.server` from inside `scuppet-media/`, then visit `http://localhost:8000`.

Bootstrap 5, Bootstrap Icons, and the Google Fonts (Archivo + IBM Plex Sans) load from public CDNs, so an internet connection is needed for full styling.

## Brand system

| Token | Hex | Use |
|---|---|---|
| Deep navy | `#020914` | Primary background |
| Panel navy | `#0B121D` | Alternating section background |
| Yellow | `#FFD500` | CTAs, highlights, borders, active states |
| White | `#FFFFFF` | Headlines on dark |
| Fog | `#F4F4F0` | "Who We Work With" light section |

Typography: **Archivo** (800/900) for headlines and numerals, **IBM Plex Sans** for body copy and UI text.

## Notes on content

- All copy is taken directly from the supplied website-copy document. Nothing was invented — no stats, awards, clients, results, or contact details.
- **Testimonial quotes** were not included in the supplied document (only client names and titles were). Rather than invent quotes and attribute them to real people, each testimonial displays a clearly marked placeholder — e.g. `[Add Sudhir Gangnaik's testimonial quote here]` — on the homepage carousel and the Client Stories page. Search each file for "Add" and "testimonial quote" to find and fill these in.
- Contact details are placeholders as instructed: `[Your Email]`, `[Your Phone Number]`, `[Your LinkedIn Page]`. Update these in `contact.html` and the footer of every page (search for `[Your`).
- The supplied logo mark is used throughout: `images/logo.png` (background removed, transparent) in the navbar, mobile menu, and footer, paired with a small "MEDIA" wordmark since the mark itself only spells "Scuppet." `images/favicon.png` (derived from the same mark) is set as the site favicon.
- The contact form is frontend-only: it validates fields with vanilla JS and shows a success message, but does not send data anywhere. Wire it up to a form backend (e.g. Formspree, Netlify Forms, or your own endpoint) when ready to go live.

## Accessibility & performance

- Semantic landmarks, skip link, visible focus states, `aria-label`s on icon-only controls.
- Respects `prefers-reduced-motion`.
- Fully responsive from 320px up; no horizontal scroll at any breakpoint.
=======
# scuppet-media
>>>>>>> 4e684be4037e8f12172b10de5b7664b626cc2d71
