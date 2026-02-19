---
title: "Bootstrap & AdminLTE Patterns"
intent: "UI component and layout guidance using Bootstrap 4.6.2 and AdminLTE"
tags: ["frontend","bootstrap","adminlte","ui"]
prereqs: ["frontend-development.md"]
complexity: "beginner"
---

# Skill: Bootstrap 4.6.2 & AdminLTE v3.2.0

## Context
ChurchCRM uses **AdminLTE v3.2.0** (based on Bootstrap 4.6.2) for admin pages and dashboards. AdminLTE v3 is a modern admin template with comprehensive components and utilities built on Bootstrap 4.

---

## Key Versions
- **Bootstrap**: 4.6.2 (NOT Bootstrap 5)
- **AdminLTE**: 3.2.0 (modern version with updated components)
- **jQuery**: Used alongside Bootstrap

## Critical: Don't Mix Bootstrap Versions

**✅ Bootstrap 4.6.2 ONLY:**
```html
<button class="btn btn-primary btn-block">Full Width</button>
<div class="col-md-6 offset-md-3">Centered</div>
<div class="d-flex justify-content-center">Flex Center</div>
```

**❌ NEVER Bootstrap 5 Classes:**
```html
<!-- WRONG - These don't exist in BS4 -->
<button class="btn btn-primary w-100">Button</button>      <!-- Use btn-block -->
<div class="d-flex gap-2">Items</div>                     <!-- gap- is BS5 only -->
<div class="d-grid gap-3">Grid</div>                      <!-- d-grid is BS5 only -->
<div class="fw-bold fs-4">Text</div>                     <!-- Font utilities are BS5 -->
```

## Bootstrap 4.6.2 Grid System

### Responsive Columns
```html
<!-- Mobile: 1 col, Tablet: 2 cols, Desktop: 4 cols -->
<div class="row">
    <div class="col-lg-3 col-md-6 col-12">
        <div class="card">...</div>
    </div>
    <div class="col-lg-3 col-md-6 col-12">...</div>
    <div class="col-lg-3 col-md-6 col-12">...</div>
    <div class="col-lg-3 col-md-6 col-12">...</div>
</div>

<!-- Breakpoints in Bootstrap 4 -->
<!-- xs: <576px  (no prefix: .col-*)
     sm: ≥576px  (.col-sm-*)
     md: ≥768px  (.col-md-*)
     lg: ≥992px  (.col-lg-*)
     xl: ≥1200px (.col-xl-*) -->
```

### Offset & Centering
```html
<!-- Offset column -->
<div class="row">
    <div class="col-md-6 offset-md-3">Centered content</div>
</div>

<!-- Full width with padding -->
<div class="row">
    <div class="col-12">Full width</div>
</div>

<!-- Equal width columns -->
<div class="row">
    <div class="col">Flex: 1</div>
    <div class="col">Flex: 1</div>
    <div class="col">Flex: 1</div>
</div>
```

## AdminLTE v3 Components

### Small Boxes (Dashboard Stats)

```html
<div class="small-box bg-info">
    <div class="inner">
        <h3>150</h3>
        <p><?= gettext('Total Users') ?></p>
    </div>
    <div class="icon">
        <i class="fa-solid fa-users"></i>
    </div>
    <a href="#" class="small-box-footer">
        <?= gettext('More info') ?> 
        <i class="fa fa-arrow-circle-right"></i>
    </a>
</div>

<!-- Background Colors in AdminLTE v3 -->
<!-- bg-primary, bg-secondary, bg-success, bg-danger, bg-warning, bg-info, bg-light, bg-dark -->
```

### Cards

```html
<!-- Basic Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Users') ?></h3>
    </div>
    <div class="card-body">
        <!-- Content here -->
    </div>
    <div class="card-footer">
        <!-- Footer buttons, pagination -->
    </div>
</div>

<!-- Card with Tools (Edit/Delete/Collapse) -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('User Settings') ?></h3>
        <div class="card-tools">
            <!-- Collapse button -->
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
            <!-- Close button -->
            <button type="button" class="btn btn-tool" data-card-widget="remove">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="card-body" style="display: none;">
        <!-- Content -->
    </div>
</div>

<!-- Collapsed Card (starts closed) -->
<div class="card collapsed-card">
    <div class="card-header" style="cursor: pointer;" data-card-widget="collapse">
        <h3 class="card-title"><?= gettext('Advanced Settings') ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-plus"></i>  <!-- Shows plus when closed -->
            </button>
        </div>
    </div>
    <div class="card-body" style="display: none;">
        <!-- Content initially hidden -->
    </div>
</div>
```

### Data Tables

```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Users List') ?></h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Email') ?></th>
                    <th><?= gettext('Status') ?></th>
                    <th><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <tr>
                    <td><?= InputUtils::escapeHTML($user->getName()) ?></td>
                    <td><?= InputUtils::escapeHTML($user->getEmail()) ?></td>
                    <td>
                        <span class="badge badge-success">Active</span>
                    </td>
                    <td>
                        <a href="edit?id=<?= $user->getId() ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-pencil"></i> <?= gettext('Edit') ?>
                        </a>
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $user->getId() ?>)">
                            <i class="fa-solid fa-trash"></i> <?= gettext('Delete') ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

### Badges & Labels

```html
<!-- Badges (all sizes) -->
<span class="badge badge-primary">Primary</span>
<span class="badge badge-success">Success</span>
<span class="badge badge-danger">Danger</span>
<span class="badge badge-warning">Warning</span>
<span class="badge badge-info">Info</span>
<span class="badge badge-light">Light</span>
<span class="badge badge-dark">Dark</span>

<!-- Pill badges (more rounded) -->
<span class="badge badge-pill badge-primary">12</span>
```

## Bootstrap 4.6.2 Utilities

### Display & Visibility
```html
<!-- Display -->
<div class="d-none">Hidden</div>
<div class="d-block">Block</div>
<div class="d-inline">Inline</div>
<div class="d-inline-block">Inline-block</div>
<div class="d-flex">Flex container</div>

<!-- Responsive display -->
<div class="d-none d-md-block">Hidden on mobile, visible on tablet+</div>
<div class="d-md-none">Visible on mobile, hidden on tablet+</div>
```

### Text Alignment
```html
<div class="text-left">Left aligned</div>
<div class="text-center">Centered</div>
<div class="text-right">Right aligned</div>

<!-- Responsive alignment -->
<div class="text-center text-md-left">Center on mobile, left on tablet+</div>
```

### Spacing (Margins & Padding)
```html
<!-- Margin (m = margin) -->
<div class="m-2">All sides: 0.5rem</div>
<div class="mt-3">Top: 1rem</div>
<div class="mb-4">Bottom: 1.5rem</div>
<div class="ml-1">Left: 0.25rem</div>
<div class="mr-2">Right: 0.5rem</div>
<div class="mx-auto">Auto horizontal (center)</div>

<!-- Padding (p = padding) -->
<div class="p-3">All sides: 1rem</div>
<div class="px-2">Left & right: 0.5rem</div>
<div class="py-4">Top & bottom: 1.5rem</div>

<!-- Values: 0=0, 1=.25rem, 2=.5rem, 3=1rem, 4=1.5rem, 5=3rem -->
```

### Flexbox
```html
<!-- Flex container -->
<div class="d-flex">
    <div>Item 1</div>
    <div>Item 2</div>
</div>

<!-- Direction -->
<div class="d-flex flex-column">Column layout</div>
<div class="d-flex flex-row-reverse">Reverse order</div>

<!-- Alignment -->
<div class="d-flex justify-content-start">Left</div>
<div class="d-flex justify-content-center">Center</div>
<div class="d-flex justify-content-end">Right</div>
<div class="d-flex justify-content-between">Space between</div>
<div class="d-flex justify-content-around">Space around</div>

<!-- Vertical alignment -->
<div class="d-flex align-items-start">Top</div>
<div class="d-flex align-items-center">Center</div>
<div class="d-flex align-items-end">Bottom</div>

<!-- Fill & grow -->
<div class="d-flex">
    <div class="flex-fill">Equal width</div>
    <div class="flex-fill">Equal width</div>
</div>
```

### Colors
```html
<!-- Text colors -->
<span class="text-primary">Primary</span>
<span class="text-success">Success</span>
<span class="text-danger">Danger</span>
<span class="text-warning">Warning</span>
<span class="text-info">Info</span>

<!-- Background colors -->
<div class="bg-light">Light background</div>
<div class="bg-dark">Dark background</div>

<!-- Borders -->
<div class="border">All sides</div>
<div class="border-top">Top border only</div>
<div class="border-0">No border</div>
<div class="rounded">Rounded corners</div>
```

### Accessibility
```html
<!-- Screen reader only (hidden visually, readable by assistants) -->
<span class="sr-only">Visible only to screen readers</span>
```

## Admin Page Layout Pattern

```php
<?php
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Initialize service
$userService = new UserService();
$stats = $userService->getUserStats();
?>

<!-- Page header with title -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="nav-icon fa-solid fa-users"></i>
                    <?= gettext('User Management') ?>
                </h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= $stats['total'] ?></h3>
                        <p><?= gettext('Total Users') ?></p>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= $stats['active'] ?></h3>
                        <p><?= gettext('Active Users') ?></p>
                    </div>
                    <div class="icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?= gettext('Users') ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <a href="add.php" class="btn btn-success mb-3">
                            <i class="fa-solid fa-user-plus"></i> 
                            <?= gettext('Add New') . ' ' . gettext('User') ?>
                        </a>
                        
                        <!-- Table content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
```

## Button Groups & Sizing

```html
<!-- Buttons -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>

<!-- Sizes -->
<button class="btn btn-primary btn-lg">Large</button>
<button class="btn btn-primary">Normal</button>
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary btn-xs">Extra Small (BS4 has btn-sm)</button>

<!-- Outlines -->
<button class="btn btn-outline-primary">Outline</button>

<!-- Block buttons -->
<button class="btn btn-primary btn-block">Full width</button>

<!-- Button group -->
<div class="btn-group btn-group-sm" role="group">
    <a href="#" class="btn btn-outline-primary">Button 1</a>
    <a href="#" class="btn btn-outline-primary">Button 2</a>
    <a href="#" class="btn btn-outline-primary">Button 3</a>
</div>
```

---

## Related Knowledge
- **Grid System**: Bootstrap 4 documentation
- **Components**: AdminLTE v3 documentation
- **Icons**: FontAwesome 6 (fa-solid, fa-brands prefixes)
- **Forms**: Bootstrap form groups and validation
