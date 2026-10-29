{{--
    Makes the desktop sidebar scroll when the menu is taller than the viewport.

    Krayin renders the menu inside `#admin-sidebar > .journal-scroll` with
    `h-[calc(100vh-100px)] overflow-hidden`, so everything below the fold is cut
    off instead of becoming reachable. With the package's own entries (wallets,
    call logs, withdrawals, deposits) the last one disappears on a laptop
    screen. Copying the whole sidebar Blade just to change one utility class
    would have to be re-synced after every Krayin upgrade, so the fix ships as a
    rule that `CryptoCallCenterServiceProvider::registerSidebarScroll()` pushes
    into the layout's `styles` stack.

    Scope of the rule:
      - `@media (min-width: 1024px)` - the desktop sidebar is `max-lg:hidden`,
        the mobile drawer already scrolls on its own;
      - `.sidebar-not-collapsed` - the collapsed rail keeps Krayin's own
        `overflow-visible`, because its icon flyouts must escape the 70px rail;
      - `#admin-sidebar > .journal-scroll` - only the menu scroller, not the
        flyout submenus, the header or the mobile drawer.

    The height is left exactly as Krayin set it (`calc(100vh - 100px)`): the
    header is 60px tall and the collapse bar takes the remaining 40px at the
    bottom, so a `calc(100vh - 80px)` would slide underneath that bar.
--}}
<style data-crypto-call-center="sidebar-scroll">
    @media (min-width: 1024px) {
        .sidebar-not-collapsed #admin-sidebar > .journal-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        /* Krayin hides every `.journal-scroll` scrollbar. Here it is the only
           hint that the menu continues, so the sidebar gets a thin one back. */
        .sidebar-not-collapsed #admin-sidebar > .journal-scroll::-webkit-scrollbar {
            display: block;
            width: 8px;
        }

        .sidebar-not-collapsed #admin-sidebar > .journal-scroll::-webkit-scrollbar-track {
            background-color: transparent;
            border: 0;
        }

        .sidebar-not-collapsed #admin-sidebar > .journal-scroll::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border: 2px solid transparent;
            border-radius: 12px;
            background-clip: content-box;
        }

        .dark .sidebar-not-collapsed #admin-sidebar > .journal-scroll {
            scrollbar-color: #4b5563 transparent;
        }

        .dark .sidebar-not-collapsed #admin-sidebar > .journal-scroll::-webkit-scrollbar-thumb {
            background-color: #4b5563;
        }
    }
</style>
