# Migration Plan: Grunt CDN Download → NPM Package Management

## Objective
Replace the `grunt-curl` based CDN download system for DataTables with npm package management.

## Current State (Grunt-based)
```javascript
// Gruntfile.js
var dataTablesVer = "1.13.8";

// Downloads from CDN via grunt-curl:
- https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.8/.../datatables.min.css
- https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.8/.../datatables.min.js
- https://cdn.datatables.net/1.13.8/images/sort_*.png (5 files)
- https://cdn.datatables.net/plug-ins/1.13.8/i18n/{locale}.json (39 files)
```

**Drawbacks:**
- ❌ Requires active internet for build
- ❌ Build fails if CDN is down
- ❌ Difficult to manage versions in version control
- ❌ No security scanning of assets
- ❌ External dependency for deterministic builds

## Proposed Solution
Use npm packages to manage DataTables and related dependencies:
```json
"datatables.net": "1.13.8",
"datatables.net-bs4": "1.13.8",
"datatables.net-buttons": "2.3.6",
"datatables.net-buttons-bs4": "2.3.6",
"datatables.net-responsive": "2.4.1",
"datatables.net-responsive-bs4": "2.4.1",
"datatables.net-select": "1.6.2",
"datatables.net-select-bs4": "1.6.2"
```

## Implementation Steps

### Phase 1: Add npm Packages
- [ ] Add DataTables npm packages to `package.json`
- [ ] Run `npm ci` to validate installation
- [ ] Verify all files are present in `node_modules`

### Phase 2: Update Gruntfile.js
- [ ] Replace `curl-dir:datatables` with file copy from `node_modules/datatables.net`
- [ ] Replace `curl-dir:datatables_images` with symlink/copy from extension directories
- [ ] Replace `curl-dir:datatables_locale` with locale file handling
- [ ] Remove dependency on `grunt-curl` for DataTables
- [ ] Keep `patchDataTablesCSS` task (still needed for CSS path fixes)

### Phase 3: Update Copy Tasks
- [ ] Create new Grunt `copy` task for DataTables assets from node_modules
- [ ] Organize output directory structure to match current layout
- [ ] Ensure CSS path patching still works

### Phase 4: Update Build Script
- [ ] Modify `package.json` build:js:legacy script
- [ ] Test build process locally
- [ ] Verify all assets are correctly placed

### Phase 5: Testing
- [ ] Run `npm run build:js:legacy`
- [ ] Verify all files created in `src/skin/external/datatables/`
- [ ] Run Cypress tests to ensure no breakage
- [ ] Check CSS paths are properly patched

### Phase 6: Cleanup
- [ ] Remove `grunt-curl` from devDependencies
- [ ] Remove old CDN-based curl-dir tasks from Gruntfile
- [ ] Update documentation if needed

## Benefits
✅ Deterministic builds (no external CDN dependency)
✅ Version control friendly
✅ Security scanning via npm audit
✅ Faster builds (local copy vs CDN download)
✅ Offline build capability
✅ Better dependency management
✅ Aligned with modern npm ecosystem practices

## Directory Structure After Migration
```
src/skin/external/datatables/
├── datatables.min.css          (from node_modules/datatables.net)
├── datatables.min.js
├── pdfmake.min.js
├── vfs_fonts.js
└── DataTables-1.13.8/images/
    ├── sort_asc.png
    ├── sort_asc_disabled.png
    ├── sort_both.png
    ├── sort_desc.png
    └── sort_desc_disabled.png

src/locale/datatables/
└── *.json (39 locale files from node_modules)
```

## Risk Assessment
- **Low Risk**: All DataTables functionality remains identical
- **Backward Compatible**: No code changes required
- **Rollback Plan**: Can revert to Grunt-curl if needed

## Success Criteria
- ✅ Build completes successfully
- ✅ All DataTables assets in correct locations
- ✅ All tests pass
- ✅ No breaking changes to existing functionality
- ✅ Build no longer requires external CDN
