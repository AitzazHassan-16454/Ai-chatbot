<template>
    <div class="h-screen flex flex-col overflow-hidden">
        <header class="shrink-0 px-4 sm:px-6 py-3 border-b border-oc-border">
            <div class="max-w-3xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="/" class="w-9 h-9 rounded-lg bg-oc-surface border border-oc-border flex items-center justify-center hover:bg-oc-hover transition cursor-pointer">
                        <i class="fa-solid fa-arrow-left text-[14px] text-oc-muted"></i>
                    </a>
                    <div>
                        <h1 class="text-[15px] font-semibold text-oc-text">Profile & Job Alerts</h1>
                        <p class="text-[11px] text-oc-muted">Upload CV and set job preferences</p>
                    </div>
                </div>
                <div class="relative">
                    <button
                        type="button"
                        @click="menuOpen = !menuOpen"
                        class="w-8 h-8 rounded-md bg-oc-raised border border-oc-border flex items-center justify-center hover:bg-oc-hover hover:border-oc-border-strong transition cursor-pointer overflow-hidden select-none"
                        :class="menuOpen ? 'bg-oc-hover border-oc-border-strong' : ''"
                    >
                        <img v-if="props.user.avatar_url" :src="props.user.avatar_url" class="w-full h-full object-cover" alt="Profile" />
                        <span v-else class="text-[12px] font-semibold text-oc-primary-strong">{{ user.name.charAt(0).toUpperCase() }}</span>
                    </button>

                    <div v-if="menuOpen" class="fixed inset-0 z-40" @click="menuOpen = false"></div>

                    <div
                        v-if="menuOpen"
                        class="absolute right-0 top-full mt-2 w-56 rounded-xl bg-oc-surface border border-oc-border shadow-[0_10px_30px_rgba(0,0,0,0.6)] overflow-hidden z-50 oc-menu"
                        @click.stop
                    >
                        <div class="px-3.5 py-3 border-b border-oc-border">
                            <p class="text-[13px] font-medium text-oc-text truncate">{{ user.name }}</p>
                            <p class="text-[11px] text-oc-muted truncate">{{ user.username ? '@' + user.username : user.email }}</p>
                        </div>

                        <div class="py-1.5">
                            <button
                                @click="openEditModal"
                                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[13px] text-oc-text hover:bg-oc-hover transition cursor-pointer"
                            >
                                <i class="fa-solid fa-user-pen text-[12px] text-oc-muted w-4 text-center"></i>
                                Edit profile
                            </button>
                            <div class="my-1.5 border-t border-oc-border"></div>
                            <button
                                @click="requestSignOut"
                                class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[13px] text-oc-error hover:bg-oc-hover transition cursor-pointer"
                            >
                                <i class="fa-solid fa-arrow-right-from-bracket text-[12px] w-4 text-center"></i>
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto scrollbar-thin">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

            <div v-if="successMsg" class="bg-oc-success/10 border border-oc-success/25 rounded-lg px-4 py-3 flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-oc-success text-sm"></i>
                <p class="text-[13px] text-oc-success">{{ successMsg }}</p>
            </div>

            <div v-if="errorMsg" class="bg-oc-error/10 border border-oc-error/25 rounded-lg px-4 py-3 flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-oc-error text-sm"></i>
                <p class="text-[13px] text-oc-error">{{ errorMsg }}</p>
            </div>

            <div class="bg-oc-surface border border-oc-border rounded-xl p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center">
                        <i class="fa-solid fa-file-arrow-up text-oc-primary text-[14px]"></i>
                    </div>
                    <div>
                        <h2 class="text-[14px] font-medium text-oc-text">Upload CV / Resume</h2>
                        <p class="text-[11px] text-oc-muted">PDF format, max 10MB. AI will extract your skills automatically.</p>
                    </div>
                </div>

                <div v-if="user.cv_name && !uploading" class="flex items-center gap-3 bg-oc-raised border border-oc-border rounded-lg p-3 mb-4">
                    <i class="fa-solid fa-file-pdf text-oc-error text-[18px]"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] text-oc-text truncate">{{ user.cv_name }}</p>
                        <p class="text-[10px] text-oc-faint">Uploaded</p>
                    </div>
                    <button @click="removeCv" class="text-oc-muted hover:text-oc-error transition cursor-pointer px-2">
                        <i class="fa-solid fa-trash text-[12px]"></i>
                    </button>
                </div>

                <div
                    @dragover.prevent="cvDragActive = true"
                    @dragleave.prevent="cvDragActive = false"
                    @drop.prevent="handleCvDrop"
                    class="border border-dashed rounded-lg p-6 text-center transition-all cursor-pointer"
                    :class="cvDragActive ? 'border-oc-primary bg-oc-primary/5' : 'border-oc-border-strong hover:border-oc-primary/50'"
                    @click="$refs.cvInput.click()"
                >
                    <input ref="cvInput" type="file" accept="application/pdf" class="hidden" @change="handleCvSelect">
                    <div v-if="uploading" class="space-y-2">
                        <i class="fa-solid fa-spinner fa-spin text-oc-primary text-[20px]"></i>
                        <p class="text-[13px] text-oc-primary-strong font-medium">Analyzing your CV with AI...</p>
                        <p class="text-[11px] text-oc-muted">Extracting skills, experience & preferences</p>
                    </div>
                    <div v-else>
                        <i class="fa-solid fa-cloud-arrow-up text-oc-muted text-[22px] mb-2"></i>
                        <p class="text-[13px] text-oc-muted">{{ user.cv_name ? 'Replace CV' : 'Drop PDF here or click to browse' }}</p>
                    </div>
                </div>

                    <div v-if="parsedData" class="mt-4 bg-oc-raised border border-oc-border rounded-lg p-4 space-y-2">
                        <p class="text-[12px] text-oc-primary-strong font-medium mb-2">AI Extracted from your CV:</p>
                        <div v-if="parsedData.summary" class="text-[12px] text-oc-muted">{{ parsedData.summary }}</div>
                        <div v-if="parsedData.job_titles && parsedData.job_titles.length" class="flex flex-wrap gap-1.5 mt-1">
                            <span v-for="t in parsedData.job_titles" :key="t" class="oc-chip-active">{{ t }}</span>
                        </div>
                        <div v-if="parsedData.skills && parsedData.skills.length" class="flex flex-wrap gap-1.5 mt-1">
                            <span v-for="s in parsedData.skills.slice(0, 10)" :key="s" class="oc-chip">{{ s }}</span>
                        </div>
                    </div>
            </div>

            <div class="bg-oc-surface border border-oc-border rounded-xl p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center">
                        <i class="fa-solid fa-sliders text-oc-primary text-[14px]"></i>
                    </div>
                    <div>
                        <h2 class="text-[14px] font-medium text-oc-text">Job Preferences</h2>
                        <p class="text-[11px] text-oc-muted">Set your search criteria for daily job alerts</p>
                    </div>
                </div>

                <form @submit.prevent="savePreferences" class="space-y-4">
                    <div>
                        <label class="block text-[12px] text-oc-muted mb-1.5">Keywords / Job Title</label>
                        <input v-model="form.job_keywords" type="text" placeholder="e.g. Python Developer, React Engineer"
                            class="oc-input" />
                    </div>

                    <div>
                        <label class="block text-[12px] text-oc-muted mb-1.5">Location</label>
                        <input v-model="form.job_location" type="text" placeholder="e.g. Karachi, Pakistan or Remote"
                            class="oc-input" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Job Type</label>
                            <select v-model="form.job_type" class="oc-select">
                                <option value="full_time">Full-time</option>
                                <option value="part_time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Remote</label>
                            <select v-model="form.job_remote" class="oc-select">
                                <option value="any">Any</option>
                                <option value="remote">Remote</option>
                                <option value="onsite">On-site</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Experience</label>
                            <select v-model="form.job_experience" class="oc-select">
                                <option value="entry">Entry Level</option>
                                <option value="mid">Mid Level</option>
                                <option value="senior">Senior Level</option>
                                <option value="executive">Executive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Frequency</label>
                            <select v-model="form.job_alert_frequency" class="oc-select">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Min Salary (USD/yr)</label>
                            <input v-model.number="form.job_salary_min" type="number" min="0" placeholder="e.g. 30000"
                                class="oc-input" />
                        </div>
                        <div>
                            <label class="block text-[12px] text-oc-muted mb-1.5">Max Salary (USD/yr)</label>
                            <input v-model.number="form.job_salary_max" type="number" min="0" placeholder="e.g. 80000"
                                class="oc-input" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-oc-raised border border-oc-border rounded-lg px-4 py-3">
                        <div>
                            <p class="text-[13px] text-oc-text">Enable Job Alerts</p>
                            <p class="text-[10px] text-oc-muted">Receive daily email with matching jobs</p>
                        </div>
                        <button type="button" @click="form.job_alerts_enabled = !form.job_alerts_enabled"
                            class="w-10 h-6 rounded-full transition-colors cursor-pointer relative"
                            :class="form.job_alerts_enabled ? 'bg-oc-primary' : 'bg-oc-border-strong'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow"
                                :class="form.job_alerts_enabled ? 'translate-x-4' : ''"></span>
                        </button>
                    </div>

                    <button type="submit" :disabled="savingPrefs" class="oc-btn oc-btn-primary w-full">
                        <i v-if="savingPrefs" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ savingPrefs ? 'Saving...' : 'Save Preferences' }}
                    </button>
                </form>
            </div>

            <div class="bg-oc-surface border border-oc-border rounded-xl p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-oc-raised border border-oc-border flex items-center justify-center">
                            <i class="fa-solid fa-magnifying-glass text-oc-primary text-[14px]"></i>
                        </div>
                        <div>
                            <h2 class="text-[14px] font-medium text-oc-text">Search Jobs Now</h2>
                            <p class="text-[11px] text-oc-muted">Run an immediate search with your preferences</p>
                        </div>
                    </div>
                    <button @click="searchJobs" :disabled="searching || !form.job_keywords"
                        class="oc-btn oc-btn-primary w-auto px-4 py-2 text-[12px]">
                        <i v-if="searching" class="fa-solid fa-spinner fa-spin text-[12px]"></i>
                        {{ searching ? 'Searching...' : 'Search' }}
                    </button>
                </div>

                <div v-if="searchResult" class="mb-4 text-[12px] text-oc-muted">
                    Found <span class="text-oc-primary-strong font-medium">{{ searchResult.found }}</span> jobs,
                    <span class="text-oc-success">{{ searchResult.new }}</span> new.
                </div>

                <div v-if="jobs.length" class="space-y-2 max-h-[400px] overflow-y-auto scrollbar-thin">
                    <a v-for="job in jobs" :key="job.id" :href="job.url" target="_blank" rel="noopener"
                        class="block bg-oc-raised border border-oc-border rounded-lg p-3 hover:bg-oc-hover hover:border-oc-border-strong transition-colors">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] text-oc-text font-medium truncate">{{ job.title }}</p>
                                <p class="text-[12px] text-oc-muted">{{ job.company }}</p>
                            </div>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-oc-faint mt-1 shrink-0"></i>
                        </div>
                        <div class="flex items-center gap-3 mt-1.5 text-[10px] text-oc-faint">
                            <span v-if="job.location"><i class="fa-solid fa-location-dot mr-1"></i>{{ job.location }}</span>
                            <span v-if="job.salary_text"><i class="fa-solid fa-money-bill mr-1"></i>{{ job.salary_text }}</span>
                            <span v-if="job.posted_at"><i class="fa-regular fa-clock mr-1"></i>{{ job.posted_at }}</span>
                        </div>
                    </a>
                </div>

                <div v-else-if="!searching" class="text-center py-6">
                    <p class="text-[12px] text-oc-faint">No jobs found yet. Set preferences and click Search.</p>
                </div>
            </div>

        </div>
        </div>

        <EditProfileModal
            :show="showEditModal"
            :user="props.user"
            @close="showEditModal = false"
            @updated="handleProfileUpdated"
        />

        <SignOutModal
            :show="showSignOutModal"
            :name="user.name"
            @close="showSignOutModal = false"
            @confirm="logout"
        />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import EditProfileModal from '@/Components/EditProfileModal.vue';
import SignOutModal from '@/Components/SignOutModal.vue';

var props = defineProps({
    user: Object,
});

var uploading = ref(false);
var savingPrefs = ref(false);
var searching = ref(false);
var cvDragActive = ref(false);
var successMsg = ref('');
var errorMsg = ref('');
var parsedData = ref(null);
var jobs = ref([]);
var searchResult = ref(null);
var menuOpen = ref(false);
var showEditModal = ref(false);
var showSignOutModal = ref(false);

var form = reactive({
    job_keywords: props.user.job_keywords || '',
    job_location: props.user.job_location || '',
    job_type: props.user.job_type || 'full_time',
    job_remote: props.user.job_remote || 'any',
    job_experience: props.user.job_experience || 'mid',
    job_salary_min: props.user.job_salary_min || null,
    job_salary_max: props.user.job_salary_max || null,
    job_alerts_enabled: props.user.job_alerts_enabled || false,
    job_alert_frequency: props.user.job_alert_frequency || 'daily',
});

function clearMessages() {
    successMsg.value = '';
    errorMsg.value = '';
}

function handleCvSelect(event) {
    if (event.target.files.length > 0) uploadCv(event.target.files[0]);
    event.target.value = '';
}

function handleCvDrop(event) {
    cvDragActive.value = false;
    if (event.dataTransfer.files.length > 0) uploadCv(event.dataTransfer.files[0]);
}

async function uploadCv(file) {
    if (file.type !== 'application/pdf') {
        errorMsg.value = 'Only PDF files are accepted.';
        return;
    }
    clearMessages();
    uploading.value = true;
    parsedData.value = null;

    var formData = new FormData();
    formData.append('cv', file);

    try {
        var response = await axios.post('/profile/cv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        successMsg.value = 'CV uploaded and analyzed successfully!';
        props.user.cv_name = response.data.cv_name;
        if (response.data.parsed) {
            parsedData.value = response.data.parsed;
            if (response.data.parsed.preferred_location && !form.job_location) {
                form.job_location = response.data.parsed.preferred_location;
            }
            if (response.data.search_query && !form.job_keywords) {
                form.job_keywords = response.data.search_query;
            }
            if (response.data.parsed.salary_min && !form.job_salary_min) {
                form.job_salary_min = response.data.parsed.salary_min;
            }
            if (response.data.parsed.salary_max && !form.job_salary_max) {
                form.job_salary_max = response.data.parsed.salary_max;
            }
        }
    } catch (err) {
        errorMsg.value = err.response?.data?.error || 'Failed to upload CV.';
    } finally {
        uploading.value = false;
    }
}

async function removeCv() {
    clearMessages();
    try {
        await axios.delete('/profile/cv');
        props.user.cv_name = null;
        parsedData.value = null;
        successMsg.value = 'CV removed.';
    } catch (err) {
        errorMsg.value = 'Failed to remove CV.';
    }
}

async function savePreferences() {
    clearMessages();
    savingPrefs.value = true;
    try {
        await axios.put('/profile/job-preferences', form);
        successMsg.value = 'Preferences saved!';
    } catch (err) {
        errorMsg.value = err.response?.data?.error || 'Failed to save preferences.';
    } finally {
        savingPrefs.value = false;
    }
}

async function searchJobs() {
    clearMessages();
    searching.value = true;
    searchResult.value = null;
    try {
        var response = await axios.post('/job-alerts/search', {
            keywords: form.job_keywords,
            location: form.job_location,
            job_type: form.job_type,
            remote: form.job_remote,
            experience: form.job_experience,
        });
        searchResult.value = { found: response.data.found, new: response.data.new };
        if (response.data.jobs && response.data.jobs.length) {
            jobs.value = response.data.jobs.concat(jobs.value);
        }
    } catch (err) {
        errorMsg.value = err.response?.data?.error || 'Search failed.';
    } finally {
        searching.value = false;
    }
}

function openEditModal() {
    menuOpen.value = false;
    showEditModal.value = true;
}

function handleProfileUpdated(updates) {
    Object.assign(props.user, updates);
}

function requestSignOut() {
    menuOpen.value = false;
    showSignOutModal.value = true;
}

function logout() {
    router.post('/logout');
}

onMounted(async function () {
    try {
        var response = await axios.get('/job-alerts');
        jobs.value = response.data.jobs;
    } catch (e) {}
});
</script>
