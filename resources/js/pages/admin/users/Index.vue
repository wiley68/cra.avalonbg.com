<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role_name: string;
    must_change_password: boolean;
    is_system_admin: boolean;
};

defineProps<{
    users: UserRow[];
}>();
</script>

<template>
    <Head title="Admin users" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Users</h1>
                <p class="text-sm text-muted-foreground">Manage users and role assignments.</p>
            </div>

            <Button as-child>
                <Link href="/admin/users/create">Create user</Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Flags</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t">
                        <td class="px-4 py-3">{{ user.name }}</td>
                        <td class="px-4 py-3">{{ user.email }}</td>
                        <td class="px-4 py-3">{{ user.role_name }}</td>
                        <td class="px-4 py-3">
                            <span v-if="user.is_system_admin">System admin</span>
                            <span v-if="user.must_change_password" class="ml-2">Force password</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="`/admin/users/${user.id}/edit`">Edit</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

