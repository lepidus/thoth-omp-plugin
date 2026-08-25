<?php

final class ThothMenuHandler
{
    private object $request;
    public function __construct(object $request)
    {
        $this->request = $request;
    }

    public function addMenu(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $router = $this->request->getRouter();
        $roles = (array) $router->getHandler()
            ->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        $menu = $templateManager->getState('menu');
        if ($menu === [] || !in_array(ROLE_ID_MANAGER, $roles, true)) {
            return false;
        }

        $item = [
            'name' => __('plugins.generic.thoth.navigation.thoth'),
            'url' => $router->url($this->request, null, 'thoth'),
            'isCurrent' => $router->getRequestedPage($this->request) === 'thoth',
            'icon' => 'Book',
        ];
        $offset = array_search('settings', array_keys($menu), true);
        if ($offset === false) {
            $menu['thoth'] = $item;
        } else {
            $menu = array_slice($menu, 0, $offset, true)
                + ['thoth' => $item]
                + array_slice($menu, $offset, null, true);
        }
        $templateManager->setState(['menu' => $menu]);
        return false;
    }
}
