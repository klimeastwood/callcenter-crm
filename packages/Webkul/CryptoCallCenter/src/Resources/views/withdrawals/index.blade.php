<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('crypto_call_center::app.withdrawals.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header Section -->
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('crypto_call_center.withdrawals.index.header.before') !!}

                <div class="text-xl font-bold dark:text-white">
                    @lang('crypto_call_center::app.withdrawals.index.title')
                </div>

                {!! view_render_event('crypto_call_center.withdrawals.index.header.after') !!}
            </div>
        </div>

        {!! view_render_event('crypto_call_center.withdrawals.index.datagrid.before') !!}

        @if ($available)
            <!-- Datagrid -->
            <x-admin::datagrid :src="route('admin.crypto_call_center.withdrawals.index')">
                <!-- DataGrid Shimmer -->
                <x-admin::shimmer.datagrid />
            </x-admin::datagrid>
        @else
            <!-- The tables belong to the trading application and it has not deployed them. -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900">
                @lang('crypto_call_center::app.withdrawals.index.unavailable')
            </div>
        @endif

        {!! view_render_event('crypto_call_center.withdrawals.index.datagrid.after') !!}
    </div>
</x-admin::layouts>
