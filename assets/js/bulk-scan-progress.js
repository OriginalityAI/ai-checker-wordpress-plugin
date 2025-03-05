jQuery(document).ready(function ($) {
    const totalPosts = bulkScanData.totalPosts;
    if (totalPosts > 0) {
        const interval = setInterval(() => {
            $.ajax({
                url: bulkScanData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bulk_scan_progress',
                    bulk_scan_nonce: bulkScanData.scanNonce,
                },
                success(response) {
                    if (response.success) {
                        const { total_posts, completed_posts, failed_posts, remaining_posts } = response.data;

                        // Update the progress notice dynamically
                        let progressMessage = `
                            <div style="font-size: 14px; line-height: 1.6; margin-bottom: 10px;">
                                <strong style="color: green;">${completed_posts.length}</strong> out of <strong>${total_posts}</strong> posts scanned successfully.<br>
                                <strong style="color: red;">${failed_posts.length}</strong> posts failed.<br>
                                <strong style="color: orange;">${remaining_posts}</strong> posts are still in progress.
                            </div>
                        `;
                        $('#bulk-scan-progress').html(progressMessage);

                        // Update columns for completed posts
                        completed_posts.forEach(post => {
                            const { post_id, scan_result } = post;
                            const originalityaiMainColumn = $(`#post-${post_id} .column-originalityai`);
                            const originalityaiRefreshColumn = $(`#post-${post_id} .originalityai-refresh`);
                            const originalityaiShareColumn = $(`#post-${post_id} .originalityai-share`);

                            // Update the main column with scan results
                            if (originalityaiMainColumn.length && scan_result) {
                                const { score_original, score_ai, record_id, color_mapping_item, max } = scan_result;

                                originalityaiMainColumn.html(`
                                    <span id="col-originalityai--${post_id}">
                                        <span style="color: ${color_mapping_item['color']}">
                                            ${color_mapping_item['label']} 
                                            <a target="_blank" href="https://app.originality.ai/home/content-scan/${record_id}">(View Scan)</a><br>
                                            ${max}% confidence
                                        </span>
                                    </span>
                                `);
                            }

                            // Update the refresh column
                            if (originalityaiRefreshColumn.length) {
                                const refreshLink = originalityaiRefreshColumn.find('[data-originalityai-tooltip]');
                                if (refreshLink.length) {
                                    refreshLink.removeClass('originalityai-rotate-child-img'); // Remove rotating class
                                    refreshLink.css({
                                        'pointer-events': 'auto',
                                        'cursor': 'pointer',
                                    });
                                    refreshLink.find('svg path').css('fill', '#156FB9'); // Change SVG color to blue
                                }
                            }

                            // Update the share column
                            if (originalityaiShareColumn.length) {
                                const shareLink = originalityaiShareColumn.find('[data-originalityai-tooltip]');
                                if (shareLink.length) {
                                    shareLink.css({
                                        'pointer-events': 'auto',
                                        'cursor': 'pointer',
                                    });
                                    shareLink.find('svg path').css('fill', '#156FB9'); // Change SVG color to blue
                                }
                            }
                        });

                        failed_posts.forEach(post => {
                            const { post_id, error_message } = post;
                            const originalityaiMainColumn = $(`#post-${post_id} .column-originalityai`);
                            const originalityaiRefreshColumn = $(`#post-${post_id} .originalityai-refresh`);

                            // Update the main column with error message
                            if (originalityaiMainColumn.length) {
                                originalityaiMainColumn.html(`<span style="color: red;">${error_message}</span>`);
                            }

                            // Update the refresh column
                            if (originalityaiRefreshColumn.length) {
                                const refreshLink = originalityaiRefreshColumn.find('[data-originalityai-tooltip]');
                                if (refreshLink.length) {
                                    refreshLink.removeClass('originalityai-rotate-child-img');
                                }
                            }
                        });

                        // Stop polling when all scans are completed
                        if (remaining_posts <= 0) {
                            clearInterval(interval);

                            let finalMessage = `
                                <div style="margin-top: 10px; font-size: 14px; line-height: 1.6;">
                                    <strong style="color: green;">All scans completed successfully!</strong>
                                    ${failed_posts.length > 0 ? `<br><strong style="color: red;">${failed_posts.length} posts failed. Check logs for details.</strong>` : ''}
                                </div>
                            `;
                            $('#bulk-scan-progress').html(progressMessage + finalMessage);
                        }
                    } else {
                        console.log('All scans completed or no posts left to process.');
                        // Stop polling and update notice for no posts in progress
                        clearInterval(interval);
                        $('#bulk-scan-progress').html('<strong>All scans completed or no posts left to process.</strong>');
                    }
                },
                error() {
                    // Stop polling and show an error message
                    clearInterval(interval);
                    $('#bulk-scan-progress').html('<strong>An error occurred while updating the scan progress. Please refresh the page to check the latest status.</strong>');
                    console.error('Failed to fetch bulk scan progress.');
                },
            });
        }, 5000); // Poll every 5 seconds
    } else {
        // No posts to scan
        $('#bulk-scan-progress').html('<strong>No posts to scan. Please initiate a new bulk scan.</strong>');
    }
});
