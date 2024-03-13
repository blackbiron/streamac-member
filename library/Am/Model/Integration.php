<?php
/**
 * Class represents records from table integrations
 * {autogenerated}
 * @property int $integration_id
 * @property string $comment
 * @property string $plugin
 * @property string $vars
 * @see Am_Table
 */
class Integration extends ResourceAbstract {
    public function getLinkTitle()
    {
        return null;
    }
}

class IntegrationTable extends ResourceAbstractTable {
    protected $_key = 'integration_id';
    protected $_table = '?_integration';
    protected $_recordClass = 'Integration';

    public function getAccessType()
    {
        return ResourceAccess::INTEGRATION;
    }
    public function getAccessTitle()
    {
        return ___('Integrations');
    }
    public function getPageId()
    {
        return 'integrations';
    }
    public function getTitleField()
    {
        return 'comment';
    }

    /** @return array of Integration */
    public function getAllowedResources(User $user, $pluginId)
    {
        $ret = [];
        foreach ($this->getDi()->resourceAccessTable
                    ->getAllowedResources($user, ResourceAccess::INTEGRATION) as $r)
            if ($r->plugin == $pluginId) $ret[] = $r;
        return $ret;
    }
}
