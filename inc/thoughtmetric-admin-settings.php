<?php

function thoughtmetric_base64decode( $input ) {
    return base64_decode(base64_decode( $input ));
}

//register settings
function thoughtmetric_register_settings() {
    add_option( 'thoughtmetric_snippet' );
    register_setting( 'thoughtmetric_options_group', 'thoughtmetric_snippet', array( 'sanitize_callback' => 'thoughtmetric_base64decode' ) );
}
add_action( 'admin_init', 'thoughtmetric_register_settings' );

//register options page
function thoughtmetric_register_options_page() {
    add_options_page( 'ThoughtMetric Settings', 'ThoughtMetric', 'manage_options', 'thoughtmetric', 'thoughtmetric_options_page_html' );
}
add_action( 'admin_menu', 'thoughtmetric_register_options_page' );

function thoughtmetric_base64encode() {
?>
<script type="text/javascript">
var tm_submit = document.getElementById( 'submit' )
var tm_snippet_unencoded = document.getElementById( 'tm_snippet_unencoded' );
tm_submit.disabled = tm_snippet_unencoded.value === '';

tm_submit.addEventListener( 'click', function() {
    var thoughtmetric_snippet = document.getElementById( 'thoughtmetric_snippet' );
    thoughtmetric_snippet.value = window.btoa(window.btoa( tm_snippet_unencoded.value ));
});

tm_snippet_unencoded.addEventListener( 'input', function() {
    tm_submit.disabled = tm_snippet_unencoded.value === '';
}, false);
</script>
<?php
}
add_action( 'admin_footer', 'thoughtmetric_base64encode' );



function thoughtmetric_options_page_html() {
    ?>
<div>
    <div id="tm-logo">
        <img src="<?php echo esc_url( plugins_url( 'images/tm-logo.png', dirname( __FILE__ ) ) ); ?>" width="340px" alt="ThoughtMetric logo" />
    </div>
    <div id="tm-wrap">
        <div>
            <h2><?php esc_html_e( 'ThoughtMetric for WooCommerce', 'thoughtmetric' ); ?></h2>
            <h3>Understand Your Ecommerce Marketing Performance</h3>
            <form method="post" action="options.php">
                <?php settings_fields( 'thoughtmetric_options_group' ); ?>
                <h4>Tracking code</h4>
                <p class="tm-p">Paste your ThoughtMetric tracking code and click <span class="tm-medium-font">Save Changes</span>.</p>
                <textarea id="tm_snippet_unencoded" rows="8"><?php echo esc_textarea(get_option( 'thoughtmetric_snippet' )); ?></textarea>
                <input type="hidden" id="thoughtmetric_snippet" name="thoughtmetric_snippet" />
                <?php submit_button( 'Save Changes' ); ?>
            </form>
        </div>
    </div>
</div>
    <?php
}
