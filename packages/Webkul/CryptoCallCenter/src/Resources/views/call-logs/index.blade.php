<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('crypto_call_center::app.call-logs.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header Section -->
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('crypto_call_center.call_logs.index.header.before') !!}

                <div class="text-xl font-bold dark:text-white">
                    @lang('crypto_call_center::app.call-logs.index.title')
                </div>

                {!! view_render_event('crypto_call_center.call_logs.index.header.after') !!}
            </div>
        </div>

        {!! view_render_event('crypto_call_center.call_logs.index.datagrid.before') !!}

        <!-- Datagrid -->
        <x-admin::datagrid :src="route('admin.crypto_call_center.call_logs.index')">
            <!-- DataGrid Shimmer -->
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>

        {!! view_render_event('crypto_call_center.call_logs.index.datagrid.after') !!}
    </div>
</x-admin::layouts>
