<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

const page = usePage();
const user = page.props.auth?.user;
</script>

<template>
    <Head title="Dashboard" />

    <DashboardLayout :user="user">
        <div class="p-6">
            <!-- Content goes here -->
        </div>
    </DashboardLayout>
</template>
