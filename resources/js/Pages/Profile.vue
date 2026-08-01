<template>
    <div class="h-screen flex flex-col overflow-hidden">
        <header class="shrink-0 px-4 sm:px-6 py-3">
            <div class="max-w-3xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="/" class="w-9 h-9 rounded-xl glass flex items-center justify-center hover:bg-white/[0.08] transition cursor-pointer">
                        <i class="fa-solid fa-arrow-left text-[14px] text-[#94a3b8]"></i>
                    </a>
                    <div>
                        <h1 class="text-[15px] font-semibold text-[#eef1f8]">Profile & Job Alerts</h1>
                        <p class="text-[11px] text-[#94a3b8]">Upload CV and set job preferences</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-xl nova-gradient flex items-center justify-center">
                    <span class="text-[12px] font-semibold text-white">{{ user.name.charAt(0).toUpperCase() }}</span>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto scrollbar-glass">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

            <div v-if="successMsg" class="glass rounded-xl px-4 py-3 border-green-500/30 flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-green-400 text-sm"></i>
                <p class="text-[13px] text-green-400">{{ successMsg }}</p>
            </div>

            <div v-if="errorMsg" class="glass rounded-xl px-4 py-3 border-red-500/30 flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-red-400 text-sm"></i>
                <p class="text-[13px] text-red-400">{{ errorMsg }}</p>
            </div>

            <div class="glass-strong rounded-2xl p-5 sm:p-6 border-white/[0.1]">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-xl nova-gradient-soft flex items-center justify-center">
                        <i class="fa-solid fa-file-arrow-up nova-gradient-text text-[14px]"></i>
                    </div>
                    <div>
                        <h2 class="text-[14px] font-medium text-[#eef1f8]">Upload CV / Resume</h2>
                        <p class="text-[11px] text-[#94a3b8]">PDF format, max 10MB. AI will extract your skills automatically.</p>
                    </div>
                </div>

                <div v-if="user.cv_name && !uploading" class="flex items-center gap-3 glass rounded-xl p-3 mb-4">
                    <i class="fa-solid fa-file-pdf text-[#f87171] text-[18px]"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] text-[#eef1f8] truncate">{{ user.cv_name }}</p>
                        <p class="text-[10px] text-[#94a3b8]">Uploaded</p>
                    </div>
                    <button @click="removeCv" class="text-[#94a3b8] hover:text-[#f472b6] transition cursor-pointer px-2">
                        <i class="fa-solid fa-trash text-[12px]"></i>
                    </button>
                </div>

                <div
                    @dragover.prevent="cvDragActive = true"
                    @dragleave.prevent="cvDragActive = false"
                    @drop.prevent="handleCvDrop"
                    class="border-2 border-dashed rounded-xl p-6 text-center transition-all cursor-pointer"
                    :class="cvDragActive ? 'border-[#a5b4fc]/50 bg-[#a5b4fc]/[0.06]' : 'border-white/[0.1] hover:border-white/[0.2]'"
                    @click="$refs.cvInput.click()"
                >
                    <input ref="cvInput" type="file" accept="application/pdf" class="hidden" @change="handleCvSelect">
                    <div v-if="uploading" class="space-y-2">
                        <i class="fa-solid fa-spinner fa-spin nova-gradient-text text-[20px]"></i>
                        <p class="text-[13px] nova-gradient-text font-medium">Analyzing your CV with AI...</p>
                        <p class="text-[11px] text-[#94a3b8]">Extracting skills, experience & preferences</p>
                    </div>
                    <div v-else>
                        <i class="fa-solid fa-cloud-arrow-up text-[#94a3b8] text-[22px] mb-2"></i>
                        <p class="text-[13px] text-[#94a3b8]">{{ user.cv_name ? 'Replace CV' : 'Drop PDF here or click to browse' }}</p>
                    </div>
                </div>

                    <div v-if="parsedData" class="mt-4 glass rounded-xl p-4 space-y-2">
                        <p class="text-[12px] nova-gradient-text font-medium mb-2">AI Extracted from your CV:</p>
                        <div v-if="parsedData.summary" class="text-[12px] text-[#94a3b8]">{{ parsedData.summary }}</div>
                        <div v-if="parsedData.job_titles && parsedData.job_titles.length" class="flex flex-wrap gap-1.5 mt-1">
                            <span v-for="t in parsedData.job_titles" :key="t" class="nova-chip-active">{{ t }}</span>
                        </div>
                        <div v-if="parsedData.skills && parsedData.skills.length" class="flex flex-wrap gap-1.5 mt-1">
                            <span v-for="s in parsedData.skills.slice(0, 10)" :key="s" class="nova-chip">{{ s }}</span>
                        </div>
                    </div>
            </div>

            <div class="glass-strong rounded-2xl p-5 sm:p-6 border-white/[0.1]">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-xl nova-gradient-soft flex items-center justify-center">
                        <i class="fa-solid fa-sliders nova-gradient-text text-[14px]"></i>
                    </div>
                    <div>
                        <h2 class="text-[14px] font-medium text-[#eef1f8]">Job Preferences</h2>
                        <p class="text-[11px] text-[#94a3b8]">Set your search criteria for daily job alerts</p>
                    </div>
                </div>

                <form @submit.prevent="savePreferences" class="space-y-4">
                    <div>
                        <label class="block text-[12px] text-[#94a3b8] mb-1.5">Keywords / Job Title</label>
                        <input v-model="form.job_keywords" type="text" placeholder="e.g. Python Developer, React Engineer"
                            class="nova-input" />
                    </div>

                    <div>
                        <label class="block text-[12px] text-[#94a3b8] mb-1.5">Location</label>
                        <input v-model="form.job_location" type="text" placeholder="e.g. Karachi, Pakistan or Remote"
                            class="nova-input" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Job Type</label>
                            <select v-model="form.job_type" class="nova-select">
                                <option value="full_time">Full-time</option>
                                <option value="part_time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Remote</label>
                            <select v-model="form.job_remote" class="nova-select">
                                <option value="any">Any</option>
                                <option value="remote">Remote</option>
                                <option value="onsite">On-site</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Experience</label>
                            <select v-model="form.job_experience" class="nova-select">
                                <option value="entry">Entry Level</option>
                                <option value="mid">Mid Level</option>
                                <option value="senior">Senior Level</option>
                                <option value="executive">Executive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Frequency</label>
                            <select v-model="form.job_alert_frequency" class="nova-select">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Min Salary (USD/yr)</label>
                            <input v-model.number="form.job_salary_min" type="number" min="0" placeholder="e.g. 30000"
                                class="nova-input" />
                        </div>
                        <div>
                            <label class="block text-[12px] text-[#94a3b8] mb-1.5">Max Salary (USD/yr)</label>
                            <input v-model.number="form.job_salary_max" type="number" min="0" placeholder="e.g. 80000"
                                class="nova-input" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between glass rounded-xl px-4 py-3">
                        <div>
                            <p class="text-[13px] text-[#eef1f8]">Enable Job Alerts</p>
                            <p class="text-[10px] text-[#94a3b8]">Receive daily email with matching jobs</p>
                        </div>
                        <button type="button" @click="form.job_alerts_enabled = !form.job_alerts_enabled"
                            class="w-10 h-6 rounded-full transition-colors cursor-pointer relative"
                            :class="form.job_alerts_enabled ? 'nova-gradient' : 'bg-white/[0.1]'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow"
                                :class="form.job_alerts_enabled ? 'translate-x-4' : ''"></span>
                        </button>
                    </div>

                    <button type="submit" :disabled="savingPrefs" class="nova-btn-primary w-full">
                        <i v-if="savingPrefs" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                        {{ savingPrefs ? 'Saving...' : 'Save Preferences' }}
                    </button>
                </form>
            </div>

            <div class="glass-strong rounded-2xl p-5 sm:p-6 border-white/[0.1]">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl nova-gradient-soft flex items-center justify-center">
                            <i class="fa-solid fa-magnifying-glass nova-gradient-text text-[14px]"></i>
                        </div>
                        <div>
                            <h2 class="text-[14px] font-medium text-[#eef1f8]">Search Jobs Now</h2>
                            <p class="text-[11px] text-[#94a3b8]">Run an immediate search with your preferences</p>
                        </div>
                    </div>
                    <button @click="searchJobs" :disabled="searching || !form.job_keywords"
                        class="nova-btn-primary w-auto px-4 py-2 text-[12px]">
                        <i v-if="searching" class="fa-solid fa-spinner fa-spin text-[12px]"></i>
                        {{ searching ? 'Searching...' : 'Search' }}
                    </button>
                </div>

                <div v-if="searchResult" class="mb-4 text-[12px] text-[#94a3b8]">
                    Found <span class="nova-gradient-text font-medium">{{ searchResult.found }}</span> jobs,
                    <span class="text-green-400">{{ searchResult.new }}</span> new.
                </div>

                <div v-if="jobs.length" class="space-y-2 max-h-[400px] overflow-y-auto scrollbar-glass">
                    <a v-for="job in jobs" :key="job.id" :href="job.url" target="_blank" rel="noopener"
                        class="block glass rounded-xl p-3 hover:bg-white/[0.07] hover:-translate-y-0.5 transition-all border border-transparent hover:border-white/[0.18]">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] text-[#eef1f8] font-medium truncate">{{ job.title }}</p>
                                <p class="text-[12px] text-[#94a3b8]">{{ job.company }}</p>
                            </div>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-[#94a3b8] mt-1 shrink-0"></i>
                        </div>
                        <div class="flex items-center gap-3 mt-1.5 text-[10px] text-[#94a3b8]/70">
                            <span v-if="job.location"><i class="fa-solid fa-location-dot mr-1"></i>{{ job.location }}</span>
                            <span v-if="job.salary_text"><i class="fa-solid fa-money-bill mr-1"></i>{{ job.salary_text }}</span>
                            <span v-if="job.posted_at"><i class="fa-regular fa-clock mr-1"></i>{{ job.posted_at }}</span>
                        </div>
                    </a>
                </div>

                <div v-else-if="!searching" class="text-center py-6">
                    <p class="text-[12px] text-[#94a3b8]/60">No jobs found yet. Set preferences and click Search.</p>
                </div>
            </div>

        </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

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

onMounted(async function () {
    try {
        var response = await axios.get('/job-alerts');
        jobs.value = response.data.jobs;
    } catch (e) {}
});
</script>
