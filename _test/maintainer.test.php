<?php

/**
 * General tests for the watchcycle plugin
 *
 * @group plugin_watchcycle
 * @group plugins
 */
class maintainer_plugin_watchcycle_test extends DokuWikiTest
{

    protected $pluginsEnabled = ['watchcycle'];

    /**
     * copy over our own test users
     * @inheritDoc
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        TestUtils::rcopy(TMP_DIR, __DIR__ . '/conf');
    }


    public function test_getMaintainer()
    {
        $input = 'foo, bar, testuser1, @baz, @other';
        $output = [
            'foo' => false,
            'bar' => false,
            'testuser1' => [
                'name' => 'TestUser1',
                'mail' => 'test1@example.com',
                'pass' => '179ad45c6ce2cb97cf1029e212046e81',
                'grps' => ['user', 'other']
            ],
            '@baz' => null,
            '@other' => null,
        ];


        /** @var helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');
        $this->assertEquals($output, $helper->getMaintainers($input));
    }

    public function test_getMaintainerMails()
    {
        $input = 'foo, bar, testuser1, @baz, @other';
        $output = ['test1@example.com', 'test2@example.com'];

        /** @var helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');
        $this->assertEquals($output, $helper->getMaintainerMails($input));
    }
}
