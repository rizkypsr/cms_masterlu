# Reusable Category Panel Components

## Overview

Two reusable panel components for displaying hierarchical data with configurable actions, inspired by TanStack Table's config-based approach.

## Components

### 1. `CategoryPanel.vue`
For simple parent-child relationships (e.g., Category → Items)

### 2. `NestedCategoryPanel.vue`
For nested hierarchical data (e.g., Category → Children → Grandchildren)

## Usage Example

### Video Index Implementation

```vue
<script setup lang="ts">
import NestedCategoryPanel, { type NestedPanelColumn } from '@/components/NestedCategoryPanel.vue';

// Define column configuration
const leftPanelColumns = computed<NestedPanelColumn>(() => ({
    header: {
        accessor: 'title', // Field to display in header
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (item) => openModal('view', item),
            },
            {
                icon: 'mdi:plus',
                color: 'green',
                title: 'Add Sub Category',
                onClick: (item) => openModal('add', item),
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (item) => openModal('edit', item),
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (item) => openModal('delete', item),
            },
        ],
    },
    children: {
        accessor: 'children', // Field containing child items
        itemAccessor: 'title', // Field to display for each child
        onClick: (child) => selectChildCategory(child.id),
        actions: [
            {
                icon: 'mdi:navigation-variant',
                color: 'blue',
                title: 'View',
                onClick: (child) => openModal('view', child),
            },
            {
                icon: 'mdi:pencil',
                color: 'orange',
                title: 'Edit',
                onClick: (child) => openModal('edit', child),
            },
            {
                icon: 'mdi:delete',
                color: 'red',
                title: 'Delete',
                onClick: (child) => openModal('delete', child),
            },
        ],
    },
}));
</script>

<template>
    <NestedCategoryPanel
        :data="categories"
        :columns="leftPanelColumns"
        empty-message="Belum ada kategori"
    />
</template>
```

## Configuration Options

### NestedPanelColumn Interface

```typescript
interface NestedPanelColumn {
    header: {
        accessor: string;              // Field name to display in header
        actions?: PanelHeaderAction[]; // Action buttons for header row
    };
    children?: {
        accessor: string;              // Field name containing child items
        itemAccessor: string;          // Field name to display for each child
        onClick?: (item: any) => void; // Click handler for child items
        actions?: PanelAction[];       // Action buttons for child items
    };
}
```

### PanelHeaderAction Interface

```typescript
interface PanelHeaderAction {
    icon: string;                      // Iconify icon name
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;                    // Tooltip text
    onClick: (item: any) => void;      // Click handler (receives parent item)
}
```

### PanelAction Interface

```typescript
interface PanelAction {
    icon: string;                      // Iconify icon name
    color: 'blue' | 'green' | 'orange' | 'red';
    title?: string;                    // Tooltip text
    onClick: (item: any, parent?: any) => void; // Click handler
    show?: (item: any, parent?: any) => boolean; // Conditional visibility
}
```

## Color Options

- `blue`: `#5bc0de` (Info/Navigation)
- `green`: `#5cb85c` (Success/Add)
- `orange`: `#f0ad4e` (Warning/Edit)
- `red`: `#d9534f` (Danger/Delete)

## Features

- ✅ Config-based approach (like TanStack Table)
- ✅ Automatic Card styling with orange top border
- ✅ Responsive action buttons
- ✅ Conditional action visibility
- ✅ Click handlers for items
- ✅ Empty state message
- ✅ Hover effects
- ✅ TypeScript support

## Applying to Other Pages

### Audio Index
```typescript
const subGroupColumns = computed<NestedPanelColumn>(() => ({
    header: {
        accessor: 'name', // Audio uses 'name' instead of 'title'
        actions: [...],
    },
    children: {
        accessor: 'audios',
        itemAccessor: 'title',
        actions: [...],
    },
}));
```

### Book Index
```typescript
const chapterColumns = computed<NestedPanelColumn>(() => ({
    header: {
        accessor: 'title',
        actions: [
            {
                icon: 'mdi:file-document',
                color: 'blue',
                title: 'View Content',
                onClick: (chapter) => navigateToContent(chapter.id),
            },
            {
                icon: 'mdi:plus',
                color: 'green',
                title: 'Add Chapter',
                onClick: (chapter) => openChapterModal('add', 'noChild', undefined, chapter),
                show: (chapter) => chapter.have_child !== 0, // Conditional visibility
            },
            // ... more actions
        ],
    },
    children: {
        accessor: 'children',
        itemAccessor: 'title',
        actions: [...],
    },
}));
```

## Benefits

1. **DRY Principle**: Write panel logic once, reuse everywhere
2. **Maintainability**: Update styling/behavior in one place
3. **Consistency**: All panels look and behave the same
4. **Type Safety**: Full TypeScript support
5. **Flexibility**: Easy to customize per page via config
6. **Readability**: Config object clearly shows panel structure
