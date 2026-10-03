# Member Portal — system theme images

The system theme ships no imagery of its own: the portal header uses the church
logo that Admin -> Church Information sets, and the hero block is empty until a
theme fills it.

A church theme puts its own files here — `Include/themes/<your-church>/images/` —
and refers to them from a template with `{{ theme_asset('images/hero.jpg') }}` or
from `theme.css` with a relative `url("images/hero.jpg")`. Only these extensions
are served: `png jpg jpeg gif svg webp ico`.

This folder is part of the release and is overwritten on every upgrade; never
put a church's own files in it. See `docs/portal-themes.md`.
