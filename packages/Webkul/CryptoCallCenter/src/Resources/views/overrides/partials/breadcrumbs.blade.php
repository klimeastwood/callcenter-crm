{{--
    Override of `admin::partials.breadcrumbs` (CryptoCallCenter).

    Mirrors `packages/Webkul/Admin/src/Resources/views/partials/breadcrumbs.blade.php`
    line for line, except for one guard: every trail of `routes/breadcrumbs.php` is
    rooted at `dashboard`, and a role without the `dashboard` permission - the call
    center operator, for one - would get a "Dashboard" link to a screen that answers
    401 on every single page. The crumb is dropped instead of leading nowhere.

    A role that holds `dashboard` sees the shipped trail unchanged.

    Re-sync this file with the shipped partial after every Krayin upgrade.
--}}

@unless ($breadcrumbs->isEmpty())
    @php
        /**
         * `null` keeps the shipped trail untouched; otherwise the root crumb that
         * points at a dashboard the acting role cannot open is skipped.
         */
        $unreachableHomeUrl = bouncer()->hasPermission('dashboard')
            ? null
            : route('admin.dashboard.index');
    @endphp

    <nav aria-label="">
        <ol class="flex flex-wrap">
            @foreach ($breadcrumbs as $breadcrumb)
                @continue($unreachableHomeUrl !== null && $breadcrumb->url === $unreachableHomeUrl)

                @if (
                    $breadcrumb->url
                    && ! $loop->last
                )
                    <li class="flex items-center gap-x-1 text-sm font-normal text-brandColor dark:text-brandColor">
                        <a href="{{ $breadcrumb->url }}">
                            {{ $breadcrumb->title }}
                        </a>

                        <span class="after:content-['/'] ltr:mr-1 rtl:ml-1"></span>
                    </li>
                @else
                    <li
                        class="flex items-center gap-x-1 text-base text-gray-600 after:content-['/'] last:cursor-default after:last:hidden dark:text-gray-300"
                        aria-current="page"
                    >
                        {{ $breadcrumb->title }}
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>
@endunless
