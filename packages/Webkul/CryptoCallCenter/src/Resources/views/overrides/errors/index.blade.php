{{--
    Override of `admin::errors.index` (CryptoCallCenter).

    Mirrors `packages/Webkul/Admin/src/Resources/views/errors/index.blade.php` line
    for line, except for the home button: it always pointed at the dashboard, which a
    role without the `dashboard` permission - the call center operator, for one -
    cannot open, so the button only looped back into the same error page. It now
    points at the first section the role may open and carries that section's label,
    while a role that holds `dashboard` sees the shipped button.

    Re-sync this file with the shipped error page after every Krayin upgrade.
--}}

<x-admin::layouts.anonymous>
    <!-- Page Title -->
    <x-slot:title>
        @lang("admin::app.errors.{$errorCode}.title")
    </x-slot>

    @php
        $homeItem = bouncer()->hasPermission('dashboard')
            ? null
            : menu()->getItems('admin')->first();
    @endphp

    <!-- Error page Information -->
	<div class="flex h-[100vh] items-center justify-center bg-white dark:bg-gray-900">
        <div class="flex max-w-[745px] items-center gap-5">
            <div class="flex w-full flex-col gap-6">
                <img
                    src="{{ 
                        request()->cookie('dark_mode') 
                        ? vite()->asset('images/dark-logo.svg') 
                        : vite()->asset('images/logo.svg') 
                    }}"
                    class="w-40 ltr:pr-16 rtl:pl-16"
                >

				<div class="text-[38px] font-bold text-gray-800 dark:text-white">
                    {{ $errorCode }}
                </div>

                <p class="text-sm text-gray-800 dark:text-white">
                    @lang("admin::app.errors.{$errorCode}.description")
                </p>

                <div class="flex items-center gap-2.5">
                    <a
                        href="{{ url()->previous() }}"
                        class="cursor-pointer text-sm font-semibold text-blue-600 transition-all hover:underline"
                    >
                        @lang('admin::app.errors.go-back')
                    </a>

                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="6" height="7" viewBox="0 0 6 7" fill="none">
                            <circle cx="3" cy="3.5" r="3" fill="#9CA3AF"/>
                        </svg>
                    </span>

                    <a
                        href="{{ $homeItem?->getUrl() ?? route('admin.dashboard.index') }}"
                        class="hover:underlsine text-sm font-semibold text-blue-600 transition-all"
                    >
                        {{ $homeItem?->getName() ?? trans('admin::app.errors.dashboard') }}
                    </a>
                </div>

                <p class="text-sm text-gray-800 dark:text-white">
                    @lang('admin::app.errors.support', [
                        'link' => 'mailto:support@example.com',
                        'email' => 'support@example.com',
                        'class' => 'font-semibold text-blue-600 transition-all hover:underline',
                    ])
                </p>
            </div>

            <div class="w-full">
                <img src="{{ vite()->asset('images/error.svg') }}" />
            </div>
        </div>
	</div>
</x-admin::layouts.anonymous>
