<script setup lang="ts">
interface Movie {
  id: number
  title: string
}

interface Recommendation {
  id: number
  title: string
  score: number
  reason: string
}

const config = useRuntimeConfig()

const { data: movies } = await useFetch<Movie[]>('/movies', {
  baseURL: config.public.apiBase
})

const search = ref('')
const liked = ref<Set<number>>(new Set())
const recommendations = ref<Recommendation[]>([])
const loading = ref(false)
const error = ref('')

const filteredMovies = computed(() => {
  const list = movies.value ?? []
  const term = search.value.trim().toLowerCase()
  if (!term) return list
  return list.filter(m => m.title.toLowerCase().includes(term))
})

function toggleLike(id: number) {
  const next = new Set(liked.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  liked.value = next
}

const likedMovies = computed(() =>
  (movies.value ?? []).filter(m => liked.value.has(m.id))
)

async function getRecommendations() {
  if (liked.value.size === 0) return
  loading.value = true
  error.value = ''
  recommendations.value = []
  try {
    const res = await $fetch<{ recommendations: Recommendation[] }>('/recommend', {
      baseURL: config.public.apiBase,
      method: 'POST',
      body: { movie_ids: Array.from(liked.value) }
    })
    recommendations.value = res.recommendations
  } catch (e) {
    error.value = 'Could not fetch recommendations. Is the backend running?'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <header>
      <h1>Movie Recommender</h1>
      <p>Pick a few movies you like, and we'll suggest what to watch next based on what other viewers with similar taste enjoyed.</p>
    </header>

    <section class="picker">
      <input
        v-model="search"
        type="text"
        placeholder="Search movies..."
        class="search-input"
      >

      <ul class="movie-list">
        <li v-for="movie in filteredMovies" :key="movie.id">
          <label>
            <input
              type="checkbox"
              :checked="liked.has(movie.id)"
              @change="toggleLike(movie.id)"
            >
            {{ movie.title }}
          </label>
        </li>
      </ul>
    </section>

    <section class="selection">
      <h2>Your picks ({{ likedMovies.length }})</h2>
      <ul v-if="likedMovies.length" class="chip-list">
        <li v-for="movie in likedMovies" :key="movie.id" class="chip">
          {{ movie.title }}
          <button type="button" @click="toggleLike(movie.id)">x</button>
        </li>
      </ul>
      <p v-else class="muted">No movies selected yet.</p>

      <button
        type="button"
        class="recommend-btn"
        :disabled="likedMovies.length === 0 || loading"
        @click="getRecommendations"
      >
        {{ loading ? 'Thinking...' : 'Get Recommendations' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
    </section>

    <section v-if="recommendations.length" class="results">
      <h2>Recommended for you</h2>
      <ol>
        <li v-for="rec in recommendations" :key="rec.id">
          <div class="rec-main">
            <span class="title">{{ rec.title }}</span>
            <span class="score">match score: {{ rec.score }}</span>
          </div>
          <p class="reason">{{ rec.reason }}</p>
        </li>
      </ol>
    </section>
  </div>
</template>

<style>
body {
  margin: 0;
  font-family: system-ui, -apple-system, sans-serif;
  background: #0f172a;
  color: #e2e8f0;
}

.page {
  max-width: 720px;
  margin: 0 auto;
  padding: 2rem 1.5rem 4rem;
}

header h1 {
  margin-bottom: 0.25rem;
}

header p {
  color: #94a3b8;
  margin-top: 0;
}

.search-input {
  width: 100%;
  padding: 0.6rem 0.8rem;
  border-radius: 8px;
  border: 1px solid #334155;
  background: #1e293b;
  color: #e2e8f0;
  font-size: 1rem;
  box-sizing: border-box;
}

.movie-list {
  list-style: none;
  padding: 0;
  margin: 0.75rem 0 1.5rem;
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid #334155;
  border-radius: 8px;
}

.movie-list li {
  padding: 0.4rem 0.8rem;
  border-bottom: 1px solid #1e293b;
}

.movie-list li:last-child {
  border-bottom: none;
}

.movie-list label {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  cursor: pointer;
}

.chip-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.chip {
  background: #334155;
  padding: 0.3rem 0.5rem 0.3rem 0.8rem;
  border-radius: 999px;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
}

.chip button {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 0.9rem;
}

.muted {
  color: #64748b;
}

.recommend-btn {
  padding: 0.6rem 1.2rem;
  border-radius: 8px;
  border: none;
  background: #6366f1;
  color: white;
  font-size: 1rem;
  cursor: pointer;
}

.recommend-btn:disabled {
  background: #475569;
  cursor: not-allowed;
}

.error {
  color: #f87171;
}

.results ol {
  padding-left: 1.2rem;
}

.results li {
  padding: 0.5rem 0;
  border-bottom: 1px solid #1e293b;
}

.rec-main {
  display: flex;
  justify-content: space-between;
}

.results .score {
  color: #94a3b8;
  font-size: 0.85rem;
}

.results .reason {
  margin: 0.2rem 0 0;
  color: #64748b;
  font-size: 0.8rem;
}
</style>
