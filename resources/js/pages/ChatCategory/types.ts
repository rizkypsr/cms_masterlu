/** A saved scope item, resolved to its full path for display. */
export interface ScopeItem {
    domain: string;
    level: string;
    node_id: number;
    label: string;
    path: string[];
    missing: boolean;
}

export interface ChatCategoryNode {
    id: number;
    name: string;
    seq: number;
    is_active: boolean;
    parent_id: number | null;
    description: string | null;
    items_count?: number;
    /** Content stranded on a group node; normally empty. */
    stale_items: ScopeItem[];
    children: ChatCategoryNode[];
}

/** Row actions, provided once and injected by every node in the recursion. */
export interface NodeActions {
    add: (parent: ChatCategoryNode) => void;
    edit: (node: ChatCategoryNode) => void;
    remove: (node: ChatCategoryNode) => void;
    scope: (node: ChatCategoryNode) => void;
    staleItems: (node: ChatCategoryNode) => void;
}

