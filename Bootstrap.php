<?php


/**
 * The Bootstrap class is the main entry point of any shopware plugin.
 *
 * Short function reference
 * - install: Called a single time during (re)installation. Here you can trigger install-time actions like
 *   - creating the menu
 *   - creating attributes
 *   - creating database tables
 *   You need to return "true" or array('success' => true, 'invalidateCache' => array()) in order to let the installation
 *   be successful
 *
 * - update: Triggered when the user updates the plugin. You will get passes the former version of the plugin as param
 *   In order to let the update be successful, return "true"
 *
 * - uninstall: Triggered when the plugin is reinstalled or uninstalled. Clean up your tables here.
 */
class Shopware_Plugins_Backend_CompraThemeCompilationFix_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /** @var  array */
    private $pluginInfo;

    /**
     * Gets the plugin information
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'copyright' => $this->getPluginInfo()['copyright'],
            'label' => $this->getLabel(),
            'description' => $this->getPluginInfo()['description']['de'],
            'link' => $this->getPluginInfo()['link'],
            'author' => $this->getPluginInfo()['author'],
            'changes' => $this->getPluginInfo()['changelog'],
        );
    }

    /**
     * Gets the plugin version
     * @return mixed
     */
    public function getVersion()
    {
        return $this->getPluginInfo()['currentVersion'];
    }

    /**
     * Reads the plugin information from the plugin.json file
     * @return array
     */
    public function getPluginInfo()
    {
        if (!$this->pluginInfo) {
            $info = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
            if ($info) {
                $this->pluginInfo = $info;

                return $this->pluginInfo;
            } else {
                throw new Exception('The plugin has an invalid version file.');
            }
        }

        return $this->pluginInfo;
    }

    /**
     * Gets the plugin label
     * @return mixed
     */
    public function getLabel()
    {
        return $this->getPluginInfo()['label']['de'];
    }

    /**
     * Uninstalls the plugin
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * Updates the plugin
     * @param string $oldVersion
     * @return bool
     */
    public function update($oldVersion)
    {
        return true;
    }

    /**
     * Installs the plugin
     * @return bool
     */
    public function install()
    {
        $minimumVersion = $this->getPluginInfo()['compatibility']['minimumVersion'];
        if (!$this->assertMinimumVersion($minimumVersion)) {
            throw new \RuntimeException("At least Shopware {$minimumVersion} is required");
        }

        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Backend_Cache', 'onPostDispatchBackendCache');

        return true;
    }

    /**
     * Event handler for the Enlight_Controller_Action_PostDispatch_Backend_Cache event.
     * Used to perform dumping of the theme configuration and restarting grunt. This way we ensure that less file
     * compilation performed by grunt file watcher still works after theme compilation.
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchBackendCache(Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Theme $subject */
        $subject = $args->getSubject();
        $actionName = $subject->Request()->getActionName();
        /** @var \Shopware\Components\Logger $logger */
        $logger = $this->get('pluginlogger');

        if ($actionName === 'clearCache') {
            $logger->info("Cleared the cache!");

            // Dump theme configuration
            $logger->info("Dumping the theme configuration");
            $output = shell_exec("php {$_SERVER['DOCUMENT_ROOT']}/bin/console sw:theme:dump:configuration");
            $logger->info($output);
            unset($output);

            // Restart grunt
            $logger->info("Restarting grunt");
            // Grunt runs as pm2 process which is run under the 'vagrant' user. Since the following command is executed
            // under the 'www-data' user we need to set the HOME environment variable accordingly to the correct path.
            $output = shell_exec("env HOME=/home/vagrant pm2 restart grunt");
            $logger->info($output);
        }
    }
}
