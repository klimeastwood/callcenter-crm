<?php

namespace Webkul\CryptoCallCenter\DataGrids\Concerns;

use Webkul\DataGrid\MassAction;

/**
 * Drop the mass actions the acting user is not allowed to run.
 *
 * Krayin hides a row action when the role lacks its permission, but it adds the
 * mass actions unconditionally: "Delete" and "Update" are printed on the lead,
 * activity and organization screens for every role, and the role that lacks
 * `leads.delete` only finds out when the update request comes back as a 401.
 *
 * The permission is not guessed from the button title. Every mass action posts
 * to a named route, and the ACL config already maps that route to a permission
 * key, so the same map the middleware authorizes with decides what the grid
 * prints. A mass action whose route is unmapped stays visible: the middleware
 * authorizes it by feature, and hiding it here would be a guess.
 */
trait HidesUnauthorizedMassActions
{
    /**
     * Keep only the mass actions the acting user may actually execute.
     */
    protected function removeUnauthorizedMassActions(): void
    {
        $this->massActions = array_values(array_filter(
            $this->massActions,
            fn (MassAction $massAction) => $this->canRunMassAction($massAction),
        ));
    }

    /**
     * Decide a single mass action against the ACL map.
     */
    protected function canRunMassAction(MassAction $massAction): bool
    {
        $routeName = $this->massActionRouteName($massAction);

        if (! $routeName) {
            return true;
        }

        $permission = acl()->getRoles()->get($routeName);

        if (! $permission) {
            return true;
        }

        return bouncer()->hasPermission($permission);
    }

    /**
     * Resolve the name of the route a mass action posts to.
     *
     * The URL is compared with the registered route URIs instead of being fed to
     * the router: the mass actions carry absolute URLs, the router compares
     * paths, and the application may be served from a subdirectory, so the
     * directory the request is served from is stripped before comparing.
     */
    protected function massActionRouteName(MassAction $massAction): ?string
    {
        if (! is_string($massAction->url)) {
            return null;
        }

        $path = $this->massActionPath($massAction->url);

        if ($path === '') {
            return null;
        }

        $method = strtoupper($massAction->method);

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $path && in_array($method, $route->methods(), true)) {
                return $route->getName();
            }
        }

        return null;
    }

    /**
     * The route URI an absolute URL points at, without the serving directory.
     */
    protected function massActionPath(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        $base = trim((string) parse_url(request()->getBaseUrl(), PHP_URL_PATH), '/');

        if ($base !== '' && str_starts_with($path, $base.'/')) {
            $path = substr($path, strlen($base) + 1);
        }

        return $path;
    }
}
