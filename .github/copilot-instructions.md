# GitHub Copilot Instructions for ChurchCRM

## General Guidelines

### File Operations
- **Always use `git mv`** instead of `mv` when renaming or moving tracked files
- **Always use `git rm`** instead of `rm` when deleting tracked files
- This ensures git properly tracks file history and makes PRs clearer

### Styling and CSS
- **Never use inline `<style>` tags** in PHP files
- **Always use SCSS files** in `src/skin/scss/` directory
- **Use webpack** to compile SCSS to CSS
- **Group by feature, not by page**: Name SCSS files by feature set (e.g., `_groups.scss` for all group-related pages, not `_groupList.scss` for a single page)
- After creating/editing SCSS files, run `npm run build` to compile

### Code Organization
- Organize SCSS files by feature domain (groups, families, people, etc.)
- Each feature can span multiple pages (List, View, Editor, etc.)
- Keep related styles together in one file

### Project Structure
- SCSS files: `src/skin/scss/`
- Main SCSS entry: `src/skin/churchcrm.scss`
- JavaScript files: `src/skin/js/`
- PHP files: `src/`

## Example Workflows

### Renaming a file:
```bash
git mv old-name.scss new-name.scss
```

### Deleting a file:
```bash
git rm old-file.php
```

### Adding new styles:
1. Create or edit SCSS file in `src/skin/scss/`
2. Import in `src/skin/churchcrm.scss` using `@include meta.load-css("scss/filename");`
3. Run `npm run build` to compile
