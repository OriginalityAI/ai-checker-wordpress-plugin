<?php
/**
 * A template for scan result in the right sidebar in post editor
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div style="min-height: 200px; background-size: 102px; background-position: center 56px; background-image: url(<?php echo esc_attr( plugin_dir_url( __FILE__ ) . '../' . 'assets/img/logo.svg' ); ?>); background-repeat: no-repeat;">
    <div id="originalityai__donut_chart" style="width:100%; "></div>
    <div style="text-align: center; margin-top: -10px; line-height: 24px;">
        <div data-originalityai-tooltip-content style="font-size: 16px; font-weight: 700; display: inline-flex; align-items: center; flex-wrap: nowrap; color: <?php echo esc_attr( $record['color_mapping_item']['color'] ); ?>">
            <span style="margin-right: 4px;"><?php echo esc_html( $record['percentage'] ) . '% Confidence '; ?></span>
			<?php echo wp_kses( $svg_icon_info, OriginalityAIAdminUI::$allowed_svg_tags ); ?>
            <div data-tooltip-popup>
                How to read the AI score? <br> This score is a confidence score. <br> If the score is 90%, it should be read as "Originality.ai is 90% confident that this is AI generated" NOT that 90% of text pasted is AI generated. <br> <a href="https://originality.ai/blog/ai-content-detection-score-google/" target="_blank">Learn more</a>.
            </div>
        </div>
    </div>
    <div style="text-align: center; margin-top: 8px;">
        <div style="color: #5F5F5F; font-size: 12px; font-weight: 500; line-height: 16px; display: inline-flex; align-items: center; flex-wrap: nowrap; padding-bottom: 6px; border-bottom: 1px solid #e6e6e6; margin-bottom: 5px;">
            <span style="margin-right: 4px;"><?php echo esc_html( $current_model_name ); ?></span>
            <!-- <?php echo wp_kses( $svg_icon_info_sm, OriginalityAIAdminUI::$allowed_svg_tags ); ?> -->
        </div>
    </div>
    <div style="text-align: center; color: #5F5F5F; font-size: 12px; font-weight: 500; line-height: 16px;">
		<?php echo esc_html( $formattedDate ); ?>
    </div>
</div>