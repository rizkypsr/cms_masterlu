<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Color } from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import { TextStyle } from '@tiptap/extension-text-style';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    height?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const showCodeView = ref(false);
const htmlCode = ref('');

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            paragraph: {
                HTMLAttributes: {
                    style: 'margin: 0; line-height: 1.5; min-height: 1.5em;',
                },
            },
        }),
        Underline,
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
    ],
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        htmlCode.value = html;
        emit('update:modelValue', html);
    },
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none p-3',
        },
        handlePaste: (view, event) => {
            const text = event.clipboardData?.getData('text/plain');
            if (!text) return false;

            event.preventDefault();

            // Split by lines and preserve empty lines
            const lines = text.split('\n');
            const { state } = view;
            const { tr } = state;
            const { $from } = state.selection;
            
            // Delete current selection
            tr.deleteSelection();
            
            let pos = tr.selection.from;
            
            lines.forEach((line, index) => {
                if (index > 0) {
                    // Insert new paragraph for each line
                    const paragraph = state.schema.nodes.paragraph.create();
                    tr.insert(pos, paragraph);
                    pos += paragraph.nodeSize;
                }
                
                if (line.trim() !== '') {
                    // Insert text if line is not empty
                    const textNode = state.schema.text(line);
                    tr.insert(pos, textNode);
                    pos += line.length;
                }
            });
            
            view.dispatch(tr);
            return true;
        },
    },
});

// Initialize htmlCode
if (editor.value) {
    htmlCode.value = editor.value.getHTML();
}

watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value);
        htmlCode.value = value;
    }
});

const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleUnderline = () => editor.value?.chain().focus().toggleUnderline().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();

const toggleCodeView = () => {
    if (!showCodeView.value) {
        // Switching to code view - get current HTML
        htmlCode.value = editor.value?.getHTML() || '';
    } else {
        // Switching back to visual - update editor with HTML code
        editor.value?.commands.setContent(htmlCode.value);
        emit('update:modelValue', htmlCode.value);
    }
    showCodeView.value = !showCodeView.value;
};

const handleCodeChange = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;
    htmlCode.value = target.value;
};

const setTextColor = (color: string) => {
    editor.value?.chain().focus().setColor(color).run();
};

const setHighlight = (color: string) => {
    editor.value?.chain().focus().setHighlight({ color }).run();
};
</script>

<template>
    <div class="border border-gray-300 rounded">
        <!-- Toolbar -->
        <div class="flex items-center gap-1 border-b border-gray-300 bg-gray-50 p-2">
            <button
                v-if="!showCodeView"
                type="button"
                @click="toggleBold"
                :class="{ 'bg-gray-300': editor?.isActive('bold') }"
                class="rounded p-1.5 hover:bg-gray-200"
                title="Bold"
            >
                <Icon icon="mdi:format-bold" class="h-5 w-5" />
            </button>
            <button
                v-if="!showCodeView"
                type="button"
                @click="toggleItalic"
                :class="{ 'bg-gray-300': editor?.isActive('italic') }"
                class="rounded p-1.5 hover:bg-gray-200"
                title="Italic"
            >
                <Icon icon="mdi:format-italic" class="h-5 w-5" />
            </button>
            <button
                v-if="!showCodeView"
                type="button"
                @click="toggleUnderline"
                :class="{ 'bg-gray-300': editor?.isActive('underline') }"
                class="rounded p-1.5 hover:bg-gray-200"
                title="Underline"
            >
                <Icon icon="mdi:format-underline" class="h-5 w-5" />
            </button>
            <button
                v-if="!showCodeView"
                type="button"
                @click="toggleStrike"
                :class="{ 'bg-gray-300': editor?.isActive('strike') }"
                class="rounded p-1.5 hover:bg-gray-200"
                title="Strikethrough"
            >
                <Icon icon="mdi:format-strikethrough" class="h-5 w-5" />
            </button>
            
            <div v-if="!showCodeView" class="mx-1 h-6 w-px bg-gray-300"></div>
            
            <!-- Text Color -->
            <div v-if="!showCodeView" class="relative">
                <input
                    type="color"
                    @input="setTextColor(($event.target as HTMLInputElement).value)"
                    class="h-8 w-8 cursor-pointer rounded border-0"
                    title="Text Color"
                />
            </div>
            
            <!-- Background Color -->
            <div v-if="!showCodeView" class="relative">
                <input
                    type="color"
                    @input="setHighlight(($event.target as HTMLInputElement).value)"
                    class="h-8 w-8 cursor-pointer rounded border-0"
                    title="Background Color"
                />
            </div>
            
            <div class="mx-1 h-6 w-px bg-gray-300"></div>
            
            <button
                type="button"
                @click="toggleCodeView"
                :class="{ 'bg-gray-300': showCodeView }"
                class="rounded p-1.5 hover:bg-gray-200"
                title="Code View"
            >
                <Icon icon="mdi:code-tags" class="h-5 w-5" />
            </button>
        </div>
        
        <!-- Editor Content -->
        <div v-if="!showCodeView" class="bg-white overflow-y-auto" :style="{ height: height || '200px' }">
            <EditorContent :editor="editor" />
        </div>
        
        <!-- HTML Code View -->
        <div v-else class="bg-white">
            <textarea
                :value="htmlCode"
                @input="handleCodeChange"
                :style="{ height: height || '200px' }"
                class="w-full p-3 font-mono text-sm focus:outline-none border-0 resize-none"
                placeholder="Enter HTML code..."
            ></textarea>
        </div>
    </div>
</template>

<style>
.ProseMirror {
    min-height: 100%;
}

.ProseMirror:focus {
    outline: none;
}

.ProseMirror p {
    margin: 0;
    min-height: 1.5em;
}
</style>
