 <?php

class AdminTest extends WP_UnitTestCase
{
    private SFMF_Export $plugin;
    private array $sent_emails;
    public function setUp(): void
    {
        parent::setUp();
        wp_set_current_user(
            WP_UnitTestCase_Base::factory()->user->create(["role" => "administrator"])
        );
        $this->sent_emails = [];
        add_action('wp_mail', function ($args) {
            $this->sent_emails[] = $args;
        });
        $this->plugin = new SFMF_Export();
    }

    public function test_cron_scheduled_on_activate()
    {
        $this->assertFalse(wp_next_scheduled('sfmf_cron_hook'));

        $this->plugin->activate();
        $this->assertNotFalse(wp_next_scheduled('sfmf_cron_hook'));

        $this->plugin->deactivate();
        $this->assertFalse(wp_next_scheduled('sfmf_cron_hook'));
    }

    public function test_init()
    {
        $export = new SFMF_Export();
        $export->init();
        $this->assertNotFalse(has_action('sfmf_cron_hook', [$export, 'send_csv_attachment']));
    }

    public function test_send_csv_attachment()
    {
        $this->plugin->activate();
        update_option('sfmf_send_csv_attachment_to', 'hello@example.com');
        do_action('sfmf_cron_hook');
        $this->assertCount(1, $this->sent_emails);
        $this->assertEquals('hello@example.com', $this->sent_emails[0]['to']);
        $this->assertStringContainsString('Volunteer Export CSV', $this->sent_emails[0]['subject']);
    }

    public function test_menu()
    {
        do_action('admin_menu');
        global $submenu;
        $this->assertArrayHasKey('tools.php', $submenu);
        $slugs = array_column($submenu['tools.php'], 2);
        $this->assertContains('gf-export', $slugs);
    }

    public function test_admin_page()
    {
        do_action('admin_menu');
        set_current_screen('tools_page_gf-export');
        global $pagenow, $plugin_page;
        $pagenow = 'tools.php';
        $plugin_page = 'gf-export';
        ob_start();
        $this->plugin->settings_page_html();
        $output = ob_get_clean();
        $this->assertStringContainsString('Export Volunteers', $output);
        $this->assertStringContainsString('Download CSV', $output);
    }

    public function test_csv()
    {
        ob_start();
        try {
            $this->plugin->export_volunteers_handler();
        } catch (WPDieException $e) {
            // expected — wp_die() at end of handler
        }
        $output = ob_get_clean();
        $this->assertStringContainsString('"Created At","First Name","Last Name",Email', $output);
        $this->assertStringContainsString('Jane,Doe', $output);
    }

    public function test_ignore_fields()
    {
        ob_start();
        try {
            $this->plugin->export_volunteers_handler();
        } catch (WPDieException $e) {
            // expected — wp_die() at end of handler
        }
        $output = ob_get_clean();
        $this->assertStringNotContainsString('AdminField', $output);
        $this->assertStringNotContainsString('SectionField', $output);
    }
}
?>