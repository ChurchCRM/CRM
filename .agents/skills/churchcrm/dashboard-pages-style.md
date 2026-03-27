# Skill: Dashboard Pages — Style & Layout <!-- learned: 2026-03-23 -->

ChurchCRM dashboards follow a consistent Tabler/Bootstrap 5 pattern for layout, metrics cards, and responsive behavior.

## Page Structure

All dashboards inherit from `container-fluid`:

```php
<?php
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid">
    <!-- Page Header (optional) -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-1">
                <i class="fa-solid fa-icon-name text-primary"></i> <?= gettext('Page Title') ?>
            </h2>
            <p class="text-muted mb-0"><?= gettext('Short description of this page') ?></p>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="row mb-3">
        <!-- stat cards here -->
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- tables, lists, detailed content -->
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
```

## Stat Cards (Key Metrics)

Stat cards display quick metrics using a consistent card pattern. Each card must follow this exact structure:

### Column Grid

**Mobile-first responsive sizing** using Bootstrap 5 utilities:

```html
<div class="row mb-3">
    <!-- Primary metric: 2 columns on mobile, 3 columns on lg+ -->
    <div class="col-sm-6 col-lg-3">
        <!-- card content -->
    </div>

    <!-- Secondary metrics (same sizing) -->
    <div class="col-sm-6 col-lg-3">
        <!-- card content -->
    </div>
</div>
```

- `col-sm-6`: **50% width on small screens (SM+)** — two columns across
- `col-lg-3`: **25% width on large screens (LG+)** — four columns across
- **Always** add `mb-3` to the row for spacing

### Card Component

```html
<div class="card card-sm">
    <div class="card-body">
        <div class="row align-items-center">
            <!-- Icon Column -->
            <div class="col-auto">
                <span class="bg-primary text-white avatar rounded-circle">
                    <i class="fa-solid fa-users icon"></i>
                </span>
            </div>

            <!-- Value Column -->
            <div class="col">
                <div class="fw-medium">42</div>
                <div class="text-muted">Total Users</div>
            </div>
        </div>
    </div>
</div>
```

### Key Classes & Properties

| Element | Classes | Purpose |
|---------|---------|---------|
| Card | `card card-sm` | Small, compact card style |
| Layout | `row align-items-center` | Vertical center icon & value |
| Icon wrapper | `bg-{color} text-white avatar rounded-circle` | Colored circular badge |
| Icon element | `fa-solid fa-{icon} icon` | FontAwesome icon **with `icon` class** |
| Value | `fw-medium` | Font-weight 600; matches dashboard aesthetics |
| Label | `text-muted` | Muted gray color for context |

### Avatar Icon Colors

Use semantic Bootstrap color utilities:

- `.bg-primary`: Main/primary metrics
- `.bg-success`: Active, approved, positive metrics
- `.bg-warning`: Caution, review needed
- `.bg-danger`: Error, locked, critical
- `.bg-info`: Informational, secondary
- `.bg-secondary`: Neutral, inactive

Example:
```html
<span class="bg-success text-white avatar rounded-circle">
    <i class="fa-solid fa-check-circle icon"></i>
</span>
```

### Typography

- **Metric value**: Use `.fw-medium` (600 weight)
  - ✅ CORRECT: `<div class="fw-medium">42</div>`
  - ❌ WRONG: `<div class="h3 m-0">42</div>` (too bold, wrong semantics)
- **Label**: Use `.text-muted` for readability
- Never use heading classes (`h3`, `h4`) for metric values

## Common Stat Card Patterns

### 4-Card Finance Metrics

```php
<div class="row mb-3">
    <!-- Total Revenue -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-hand-holding-dollar icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= number_format($totalRevenue, 2) ?></div>
                        <div class="text-muted">Total Revenue</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Funds -->
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-piggy-bank icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $activeFunds ?></div>
                        <div class="text-muted">Active Funds</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

### 4-Card User/Group Metrics

```php
<div class="row mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $totalUsers ?></div>
                        <div class="text-muted">Total Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $activeUsers ?></div>
                        <div class="text-muted">Active Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-lock icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $lockedUsers ?></div>
                        <div class="text-muted">Locked Users</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-shield-alt icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $twoFactorEnabled ?></div>
                        <div class="text-muted">2FA Enabled</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

## Spacing & Margins

- Row container: Always add **`mb-3`** after stat cards for spacing to main content
- Card body: Uses `.card-body` padding (default Bootstrap)
- No custom margin utilities needed on individual cards
- Gap between stat cards handled by Bootstrap's grid gutters

## Responsive Behavior

ChurchCRM dashboards are **mobile-first** using Bootstrap's breakpoints:

- **Mobile (< 576px)**: Single column (stat cards stack)
- **Small (SM, ≥ 576px)**: Two columns per row (`col-sm-6`)
- **Large (LG, ≥ 992px)**: Four columns per row (`col-lg-3`)

Grid is **never** hardcoded to specific column counts — always use responsive column classes.

## Common Anti-Patterns

❌ **Wrong** — Fixed column on all screens:
```html
<div class="col-lg-3"><!-- card --></div>
```

✅ **Correct** — Mobile-first responsive:
```html
<div class="col-sm-6 col-lg-3"><!-- card --></div>
```

---

❌ **Wrong** — Using heading classes for metrics:
```html
<div class="h3 m-0"><?= $count ?></div>
```

✅ **Correct** — Using font-weight utility:
```html
<div class="fw-medium"><?= $count ?></div>
```

---

❌ **Wrong** — Missing `icon` class on FontAwesome:
```html
<i class="fa-solid fa-users"></i>
```

✅ **Correct** — With `icon` class for sizing alignment:
```html
<i class="fa-solid fa-users icon"></i>
```

---

❌ **Wrong** — No spacing class on row:
```html
<div class="row">
    <!-- stat cards -->
</div>
```

✅ **Correct** — With `mb-3` for section spacing:
```html
<div class="row mb-3">
    <!-- stat cards -->
</div>
```

## Tools & References

- **Tabler Docs**: [tabler.io](https://tabler.io) — avatar, card, grid patterns
- **Bootstrap 5**: Column grid, utilities (`fw-medium`, `text-muted`, `bg-*`)
- **FontAwesome 6.5**: Icon library used throughout ChurchCRM
- **CSS Variables**: Tabler provides `--tblr-*` tokens in Bootstrap build
