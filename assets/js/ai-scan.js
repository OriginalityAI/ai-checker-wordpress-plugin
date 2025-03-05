jQuery(document).ready(function ($) {
  // Listen for clicks on all elements with the class "start-scan-link"
  $(document).on('click', '.start-scan-link, .refresh-scan-link', function (event) {
      event.preventDefault(); // Prevent the default behavior

      // Get the post ID from the clicked element's data attribute
      const postId = $(this).data('post-id');

      // Hide the "not started" message
      $(`#originality_ai_scan_not_started_${postId}`).hide();

      // check if the element has the class "refresh-scan-link"
      const isRefreshScan = $(this).hasClass('refresh-scan-link');

      // Call the scan function
      send_for_scan(postId, this, !isRefreshScan);
  });
});


function send_for_scan(post_id, obj, replaceWith = true) {
  let originalityai_main_column = jQuery(obj).closest('tr').find('td.originalityai');
  let originalityai_refresh_column = jQuery(obj).closest('tr').find('td.originalityai-refresh');
  let originalityai_refresh_link = originalityai_refresh_column.find('[data-originalityai-tooltip]')

  let originalityai_share_column = jQuery(obj).closest('tr').find('td.originalityai-share');
  let originalityai_share_link = originalityai_share_column.find('[data-originalityai-tooltip]');

  if (replaceWith) {
    jQuery(obj).replaceWith('<span>Scan in Progress (Try Refreshing Page)<br></span>');
  }
  if (!replaceWith) {
    jQuery(obj).addClass('originalityai-rotate-child-img');
  }


  jQuery.ajax({
    url: aiScanData.ajaxurl, 
    type: 'POST',
    data: {
      post_id: post_id,
      action: 'ai_scan',
      scan_nonce: aiScanData.nonce
    },
    success(response) {
      // Do something with the response
      if (!replaceWith) {
        jQuery(obj).removeClass('originalityai-rotate-child-img');
      }

      if (response.hasOwnProperty('success') && response.success) {
        const scoreOriginal = response.data?.raw?.score?.original;
        const scoreAi = response.data?.raw?.score?.ai;
        const res_id = response.data?.raw?.id;

        if ((typeof scoreOriginal !== 'undefined') && (typeof scoreAi !== 'undefined')) {
          const [calculatedScoreOriginal, calculatedScoreAi] = calculateIntScores(scoreOriginal);

          const color_mapping_item = getColorMappingItem( scoreAi, scoreOriginal );
          let max_val = Math.max(calculatedScoreOriginal, calculatedScoreAi);

          if (originalityai_main_column.length) {
            originalityai_main_column.html(`
              <span id='col-originalityai--${post_id}'>
                <span style='color: ${color_mapping_item.color};'>
                  ${color_mapping_item.label} <a target='_blank' href='https://app.originality.ai/home/content-scan/${res_id}'>(View Scan)</a><br> ${max_val}% confidence
                </span>
              </span>`);
          }

          const updateLinkStyle = (linkElement) => {
            if (linkElement.length && linkElement[0].hasAttribute('style')) {
              linkElement.find('svg path').css('fill', '#156fb9');
              linkElement.removeAttr('style');
            }
          };

          updateLinkStyle(originalityai_refresh_link);

          if (originalityai_share_link.length) {
            updateLinkStyle(originalityai_share_link);

            if (response.data.raw.public_link) {
              originalityai_share_link.attr({
                'href': response.data.raw.public_link,
                'target': '_blank'
              });
            }
          }
        }
      } else {
        console.log('Failed to scan the post.');
        originalityai_main_column.html('<span>Failed to scan! (Try Refreshing Page)<br></span>');
      }

    },
    error(jqXHR, textStatus, errorThrown) {
      // Handle error
      console.error(errorThrown);
    }
  });
}

function calculateIntScores(scoreOriginal1) {
  const scoreOriginal = (scoreOriginal1 * 100 >= 1) ? Math.ceil(scoreOriginal1 * 100) : 0;
  const scoreAi = 100 - scoreOriginal;

  return [scoreOriginal, scoreAi];
}

function getColorMappingItem(_scoreAi, scoreOriginalVal) {
  const [scoreOriginal, calculatedScoreAi] = calculateIntScores(scoreOriginalVal);

  const mapping = getColorsTitlesMapping();

  if (calculatedScoreAi === 100) {
    return mapping[-100];
  }
  if (calculatedScoreAi === 0) {
    return mapping[100];
  }
  if (scoreOriginal >= 90) {
    return mapping[-90];
  }
  if (scoreOriginal >= 70) {
    return mapping[-70];
  }
  if (scoreOriginal >= 60) {
    return mapping[-60];
  }
  if (scoreOriginal >= 50) {
    return mapping[-50];
  }
  if (calculatedScoreAi >= 90) {
    return mapping[90];
  }
  if (calculatedScoreAi >= 70) {
    return mapping[70];
  }
  if (calculatedScoreAi >= 50) {
    return mapping[50];
  }

  return null;
}

function getColorsTitlesMapping() {
  return {
    100: {
      color: 'rgb(104, 159, 56)',
      label: 'Likely Original',
      title: '100% confidence this post was human written.'
    },
    90: {
      color: 'rgb(235, 142, 112)',
      label: 'Likely AI',
      title: '> 90% confidence this post was generated by AI.'
    },
    70: {
      color: 'rgb(246, 179, 107)',
      label: 'Likely AI',
      title: '> 70% confidence this post was generated by AI.'
    },
    50: {
      color: 'rgb(255, 214, 102)',
      label: 'Likely AI',
      title: '50% confidence this post was generated by AI.'
    },
    '-50': {
      color: 'rgb(154, 197, 124)',
      label: 'Likely Original',
      title: '50% confidence this post was human written.'
    },
    '-60': {
      color: 'rgb(154, 197, 124)',
      label: 'Likely Original',
      title: '60% confidence this post was human written.'
    },
    '-70': {
      color: 'rgb(154, 197, 124)',
      label: 'Likely Original',
      title: '> 70% confidence this post was human written.'
    },
    '-90': {
      color: '#539D17',
      label: 'Likely Original',
      title: '> 90% confidence this post was human written.'
    },
    '-100': {
      color: '#EB4735',
      label: 'Likely AI',
      title: '100% confidence this post was generated by AI.'
    }
  };
}
