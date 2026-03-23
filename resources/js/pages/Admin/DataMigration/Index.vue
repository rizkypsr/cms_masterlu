<template>
  <div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Data Migration Tool</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="grid grid-cols-2 gap-6 mb-6">
        <div>
          <label class="block text-sm font-medium mb-2">Source Feature</label>
          <select v-model="sourceFeature" @change="onSourceChange" class="w-full border rounded px-3 py-2">
            <option value="">Select source...</option>
            <option v-for="feature in features" :key="feature" :value="feature">{{ feature }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Destination Feature</label>
          <select v-model="destFeature" @change="onDestChange" class="w-full border rounded px-3 py-2">
            <option value="">Select destination...</option>
            <option v-for="feature in features" :key="feature" :value="feature">{{ feature }}</option>
          </select>
        </div>
      </div>

      <!-- Relation Preview -->
      <div v-if="relationPreview" class="mb-6 border rounded p-4 bg-gray-50">
        <h2 class="text-xl font-semibold mb-4">Relation Structure Preview</h2>
        
        <div class="grid grid-cols-2 gap-6">
          <div>
            <h3 class="font-medium mb-2 text-blue-600">Source: {{ sourceFeature }}</h3>
            <div class="space-y-2 text-sm">
              <div v-for="(level, key) in relationPreview.source" :key="key" class="pl-4 border-l-2 border-blue-300">
                <div class="font-medium">{{ level.table }}</div>
                <div class="text-gray-600">FK: {{ level.foreign_key }}</div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="font-medium mb-2 text-green-600">Destination: {{ destFeature }}</h3>
            <div class="space-y-2 text-sm">
              <div v-for="(level, key) in relationPreview.destination" :key="key" class="pl-4 border-l-2 border-green-300">
                <div class="font-medium">{{ level.table }}</div>
                <div class="text-gray-600">FK: {{ level.foreign_key }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <h3 class="font-medium mb-2">Column Mappings (Auto)</h3>
          <div class="space-y-2 text-sm">
            <div v-for="(columns, table) in relationPreview.mappings" :key="table" class="bg-white p-2 rounded">
              <span class="font-medium">{{ table }}:</span>
              <span class="text-gray-600 ml-2">{{ columns.join(', ') }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Parent Selection -->
      <div v-if="sourceParents.length > 0" class="mb-6">
        <label class="block text-sm font-medium mb-2">Select Data to Migrate</label>
        <div class="border rounded p-4 max-h-64 overflow-y-auto">
          <label class="flex items-center mb-2">
            <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="mr-2" />
            <span class="font-medium">Select All</span>
          </label>
          <div class="border-t pt-2 space-y-2">
            <label v-for="parent in sourceParents" :key="parent.id" class="flex items-center">
              <input type="checkbox" :value="parent.id" v-model="selectedParents" class="mr-2" />
              <span>{{ parent.title }}</span>
            </label>
          </div>
        </div>
      </div>

      <div v-if="selectedParents.length > 0 && relationPreview" class="flex justify-end gap-3">
        <button @click="previewData" class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 font-medium">
          Preview Data
        </button>
      </div>
    </div>

    <!-- Data Preview -->
    <div v-if="dataPreview" class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-2xl font-semibold mb-4">Complete Data Preview After Migration</h2>
      <p class="text-sm text-gray-600 mb-4">Showing {{ dataPreview.showing }} of {{ dataPreview.total_records }} parent records with ALL relations</p>

      <div v-for="(item, index) in dataPreview.preview" :key="index" class="mb-8 border-2 rounded-lg p-4">
        <!-- Level 1: Main Record -->
        <div class="mb-4 bg-gradient-to-r from-blue-50 to-green-50 p-4 rounded-lg">
          <h3 class="text-lg font-bold mb-3">📚 {{ item.destination_table }} Record</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-medium text-gray-600">Title:</div>
              <div class="font-medium">{{ item.destination.title }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-600">Synopsis:</div>
              <div class="text-sm">{{ truncate(item.destination.synopsis, 80) }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-600">Seq:</div>
              <div>{{ item.destination.seq }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-600">Date:</div>
              <div>{{ item.destination.date }}</div>
            </div>
          </div>
          <div class="mt-2 pt-2 border-t border-gray-300">
            <span class="font-semibold">Total Chapters: {{ item.destination.chapters_count }}</span>
          </div>
        </div>

        <!-- Level 2: Chapters -->
        <div v-if="item.destination.chapters && item.destination.chapters.length > 0" class="ml-6 space-y-4">
          <ChapterTree 
            v-for="(chapter, cIndex) in item.destination.chapters" 
            :key="cIndex" 
            :chapter="chapter" 
            :index="cIndex"
            :level="0"
          />
        </div>

        <div v-else class="ml-6 text-gray-500 italic">
          No chapters found
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6 pt-4 border-t-2">
        <button @click="dataPreview = null" class="px-6 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 font-medium">
          Cancel
        </button>
        <button @click="executeMigration" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
          ✓ Confirm & Execute Migration
        </button>
      </div>
    </div>

    <div v-if="migrationResult" class="bg-green-50 border border-green-200 rounded p-4 mb-4">
      <h3 class="font-semibold text-green-800 mb-2">Migration Completed!</h3>
      <p class="text-green-700">{{ migrationResult.message }}</p>
      <p class="text-green-700">Total migrated: {{ migrationResult.migrated_count }} records</p>
      <div v-if="migrationResult.details" class="mt-2 text-sm text-green-600">
        <div>Categories: {{ migrationResult.details.category }} records</div>
        <div>Level 1: {{ migrationResult.details.level1 }} records</div>
        <div>Level 2: {{ migrationResult.details.level2 }} records</div>
        <div>Level 3: {{ migrationResult.details.level3 }} records</div>
      </div>
    </div>

    <div v-if="error" class="bg-red-50 border border-red-200 rounded p-4">
      <h3 class="font-semibold text-red-800 mb-2">Error</h3>
      <p class="text-red-700">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

defineProps({
  features: Array,
})

// Recursive Chapter Component
const ChapterTree = {
  name: 'ChapterTree',
  props: ['chapter', 'index', 'level'],
  template: `
    <div :class="['border-l-4 border-blue-400 pl-4 bg-blue-50 p-3 rounded mb-3', level > 0 ? 'ml-6' : '']">
      <h4 class="font-semibold text-blue-800 mb-2">
        {{ '📖 '.repeat(level + 1) }} Chapter {{ index + 1 }}: {{ chapter.title }}
        <span v-if="level > 0" class="text-xs text-gray-600">(Child Level {{ level }})</span>
      </h4>
      <div class="grid grid-cols-3 gap-2 text-sm mb-2">
        <div>
          <span class="text-gray-600">Seq:</span> {{ chapter.seq }}
        </div>
        <div>
          <span class="text-gray-600">Parent ID:</span> {{ chapter.parent_id || 'None' }}
        </div>
        <div>
          <span class="text-gray-600">Have Child:</span> {{ chapter.have_child }}
        </div>
      </div>
      <div class="text-sm text-gray-700 mb-2">
        <span class="font-medium">Description:</span> {{ truncate(chapter.description, 100) }}
      </div>
      <div class="font-medium text-blue-700 mb-2">
        Contents: {{ chapter.contents_count }} records
        <span v-if="chapter.children_count > 0" class="ml-2 text-purple-600">
          | Child Chapters: {{ chapter.children_count }}
        </span>
      </div>

      <!-- Contents -->
      <div v-if="chapter.contents && chapter.contents.length > 0" class="ml-4 mt-3 space-y-2">
        <div v-for="(content, coIndex) in chapter.contents" :key="coIndex" class="border-l-4 border-green-400 pl-3 bg-green-50 p-2 rounded text-sm">
          <div class="font-medium text-green-800">📄 Content {{ coIndex + 1 }} (Page {{ content.page }})</div>
          <div class="text-gray-700 mt-1">{{ truncate(content.content, 150) }}</div>
        </div>
      </div>

      <!-- Child Chapters (Recursive) -->
      <div v-if="chapter.children && chapter.children.length > 0" class="mt-4">
        <ChapterTree 
          v-for="(child, childIndex) in chapter.children" 
          :key="childIndex"
          :chapter="child"
          :index="childIndex"
          :level="level + 1"
        />
      </div>
    </div>
  `,
  methods: {
    truncate(text, length) {
      if (!text) return ''
      return text.length > length ? text.substring(0, length) + '...' : text
    }
  }
}

const sourceFeature = ref('')
const destFeature = ref('')
const sourceParents = ref([])
const selectedParents = ref([])
const selectAll = ref(false)
const relationPreview = ref(null)
const dataPreview = ref(null)
const migrationResult = ref(null)
const error = ref(null)

const onSourceChange = async () => {
  selectedParents.value = []
  selectAll.value = false
  sourceParents.value = []
  relationPreview.value = null
  dataPreview.value = null
  
  if (!sourceFeature.value) return
  
  const response = await axios.get('/admin/data-migration/parents', {
    params: { feature: sourceFeature.value }
  })
  sourceParents.value = response.data.parents
  
  if (destFeature.value) {
    loadRelationPreview()
  }
}

const onDestChange = () => {
  dataPreview.value = null
  if (sourceFeature.value && destFeature.value) {
    loadRelationPreview()
  }
}

const loadRelationPreview = async () => {
  try {
    const response = await axios.get('/admin/data-migration/relation-preview', {
      params: {
        source: sourceFeature.value,
        destination: destFeature.value
      }
    })
    relationPreview.value = response.data
    error.value = null
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load relation preview'
    relationPreview.value = null
  }
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedParents.value = sourceParents.value.map(p => p.id)
  } else {
    selectedParents.value = []
  }
}

watch(selectedParents, (newVal) => {
  selectAll.value = newVal.length === sourceParents.value.length && newVal.length > 0
  dataPreview.value = null
})

const previewData = async () => {
  error.value = null
  dataPreview.value = null

  try {
    const response = await axios.post('/admin/data-migration/preview-data', {
      source_feature: sourceFeature.value,
      destination_feature: destFeature.value,
      source_parents: selectAll.value ? ['all'] : selectedParents.value,
    })

    dataPreview.value = response.data
  } catch (err) {
    error.value = err.response?.data?.error || err.message
  }
}

const executeMigration = async () => {
  if (!confirm('Are you sure you want to execute this migration? This cannot be undone.')) {
    return
  }

  error.value = null
  migrationResult.value = null

  try {
    const response = await axios.post('/admin/data-migration/migrate', {
      source_feature: sourceFeature.value,
      destination_feature: destFeature.value,
      source_parents: selectAll.value ? ['all'] : selectedParents.value,
    })

    migrationResult.value = response.data
    dataPreview.value = null
  } catch (err) {
    error.value = err.response?.data?.error || err.message
  }
}

const truncate = (text, length) => {
  if (!text) return ''
  return text.length > length ? text.substring(0, length) + '...' : text
}
</script>
