<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Placeholder from '@tiptap/extension-placeholder'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Tulis syarat & ketentuan di sini...',
  },
  editable: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue || '',
  editable: props.editable,
  extensions: [
    StarterKit,
    Underline,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  onUpdate({ editor }) {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (val) => {
  if (!editor.value) return
  const current = editor.value.getHTML()
  if (val !== current) {
    editor.value.commands.setContent(val || '', false)
  }
})

watch(() => props.editable, (val) => {
  editor.value?.setEditable(val)
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div class="terms-editor">
    <div v-if="editable" class="terms-editor-toolbar">
      <button type="button" class="toolbar-btn" :class="{ active: editor?.isActive('bold') }"
        @click="editor?.chain().focus().toggleBold().run()" title="Bold">
        <span style="font-weight:700;font-size:13px;line-height:1">B</span>
      </button>
      <button type="button" class="toolbar-btn" :class="{ active: editor?.isActive('italic') }"
        @click="editor?.chain().focus().toggleItalic().run()" title="Italic">
        <span style="font-style:italic;font-weight:700;font-size:13px;line-height:1">I</span>
      </button>
      <button type="button" class="toolbar-btn" :class="{ active: editor?.isActive('underline') }"
        @click="editor?.chain().focus().toggleUnderline().run()" title="Underline">
        <span style="text-decoration:underline;font-weight:700;font-size:13px">U</span>
      </button>
      <div class="toolbar-sep"></div>
      <button type="button" class="toolbar-btn" :class="{ active: editor?.isActive('bulletList') }"
        @click="editor?.chain().focus().toggleBulletList().run()" title="Bullet List">
        <i class="pi pi-list"></i>
      </button>
      <button type="button" class="toolbar-btn" :class="{ active: editor?.isActive('orderedList') }"
        @click="editor?.chain().focus().toggleOrderedList().run()" title="Ordered List">
        <i class="pi pi-sort-numeric-down"></i>
      </button>
      <div class="toolbar-sep"></div>
      <button type="button" class="toolbar-btn"
        :class="{ active: editor?.isActive({ textAlign: 'left' }) }"
        @click="editor?.chain().focus().setTextAlign('left').run()" title="Align Left">
        <i class="pi pi-align-left"></i>
      </button>
      <button type="button" class="toolbar-btn"
        :class="{ active: editor?.isActive({ textAlign: 'center' }) }"
        @click="editor?.chain().focus().setTextAlign('center').run()" title="Align Center">
        <i class="pi pi-align-center"></i>
      </button>
      <button type="button" class="toolbar-btn"
        :class="{ active: editor?.isActive({ textAlign: 'right' }) }"
        @click="editor?.chain().focus().setTextAlign('right').run()" title="Align Right">
        <i class="pi pi-align-right"></i>
      </button>
    </div>
    <EditorContent :editor="editor" class="terms-editor-content" />
  </div>
</template>

<style scoped>
.terms-editor {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
}

.terms-editor-toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 6px 8px;
  border-bottom: 1px solid var(--surface-border);
  background: var(--card-bg);
  flex-wrap: wrap;
}

.toolbar-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 13px;
  transition: background 0.15s, color 0.15s;
}

.toolbar-btn:hover {
  background: var(--surface-hover, rgba(0,0,0,0.06));
  color: var(--text-primary);
}

.toolbar-btn.active {
  background: var(--primary-color, #E5534B);
  color: #fff;
}

.toolbar-sep {
  width: 1px;
  height: 20px;
  background: var(--surface-border);
  margin: 0 4px;
}

.terms-editor-content {
  padding: 10px 14px;
  min-height: 120px;
  max-height: 260px;
  overflow-y: auto;
  font-size: 13px;
  color: var(--text-primary);
  line-height: 1.6;
}

:deep(.ProseMirror) {
  outline: none;
  min-height: 100px;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: var(--text-tertiary, #aaa);
  pointer-events: none;
  float: left;
  height: 0;
}

:deep(.ProseMirror ul) {
  padding-left: 20px;
  list-style-type: disc;
}

:deep(.ProseMirror ol) {
  padding-left: 20px;
  list-style-type: decimal;
}

:deep(.ProseMirror p) {
  margin: 0 0 4px;
}

:deep(.ProseMirror strong) { font-weight: 700; }
:deep(.ProseMirror em) { font-style: italic; }
:deep(.ProseMirror u) { text-decoration: underline; }
</style>
