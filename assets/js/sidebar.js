const { PluginDocumentSettingPanel } = wp.editPost;
const { SelectControl } = wp.components;
const { useState, useEffect } = wp.element;



const { useDispatch, useSelect } = wp.data;

const pluginName = 'openai--ai-scan-sidebar';
const pluginSettingPanelName = 'ai-content-scan';
const pluginPanelFullName = pluginName + '/' + pluginSettingPanelName;

wp.plugins.registerPlugin(pluginName, {
    render: function () {
        const [spanObj, setSpanObj] = useState({ color: "", text: "" });
        const [scanLink, setScanLink] = useState('');
        const [isScanInProgress, setIsScanInProgress] = useState(false);
        const [selectedValue, setSelectedValue] = useState('');
        const [models, setModels] = useState([{ label: 'Loading...', value: 0 }]);
        const [scanResults, setScanResults] = useState(null);
        const [publicLink, setPublicLink] = useState('');

        const [chart, setChart] = useState(null);

        const [series, setSeries] = useState([]);
        const [itemColor, setItemColor] = useState('');
        const [labelColor, setLabelColor] = useState('');

        const dispatch = useDispatch();
        const isPanelOpen = useSelect((select) => {
            return select('core/edit-post').isEditorPanelOpened(pluginPanelFullName);
        }, []);
        const toggleAiContentScanPanel = (name) => {
            console.log('open panel');
            dispatch('core/edit-post').toggleEditorPanelOpened(name);
        }

        useEffect(() => {
            if (!isPanelOpen) {
                console.log(isPanelOpen, 'isEditorPanelOpened default');
                toggleAiContentScanPanel(pluginPanelFullName);
            }
        }, []);

        let scanText = '';
        if (scanResults) {
            scanText = 'Scan Again';
        } else {
            scanText = 'Start Scan';
        }

        const titleWithLogo = wp.element.createElement(
            "div",
            { id: 'originalityai__sidebar-panel-title', style: { display: 'flex', alignItems: 'center', flexWrap: "wrap" } },
            // wp.element.createElement("span", { dangerouslySetInnerHTML: {__html: ""}, style: { marginRight: '7px' } }), // AI Content Scan
            // wp.element.createElement("img", { src: originalityAIData.originality_ai__logo_img, style: { height: '1.2em', float: 'right' }})
            wp.element.createElement("svg", { width: "16", height: "12", viewBox: "0 0 16 12", fill: "none" }, 
                wp.element.createElement("path", { d: "M13.7056 8.61543C13.5823 8.44781 13.6734 8.28274 13.7344 8.22115C13.8124 8.15893 14.0179 8.00644 14.1699 7.85353C14.437 7.58497 14.5024 7.53354 14.8376 7.01925C15.2386 6.40402 15.4067 5.44784 15.154 4.65356C14.9206 3.91982 14.5705 3.305 13.6892 2.55643C12.821 1.81906 11.7313 1.28025 10.2664 0.943113C8.80159 0.605973 6.98955 0.575497 5.51444 0.885971C4.03934 1.19644 3.20138 1.58218 2.4122 2.12596C1.58015 2.69929 1.0542 3.38309 0.797392 4.21166C0.580225 4.91231 0.801501 5.37355 1.02749 5.66498C1.26678 5.97355 1.82052 6.2469 2.79639 6.05355C3.8154 5.85164 4.58583 5.39831 5.49595 5.09355C6.40608 4.78879 7.14775 4.68403 8.04555 4.68975C8.94335 4.69546 9.63982 4.92403 9.8617 5.28022C10.0836 5.63641 10.0014 5.93355 9.7487 6.2345C9.496 6.53545 8.92897 6.61926 8.53451 6.61164C8.14006 6.60402 7.61822 6.56973 7.12309 6.65354C6.62797 6.73735 6.08148 6.94306 6.07737 7.21925C6.07326 7.49544 6.24173 7.57163 6.91559 7.67639C7.58946 7.78115 8.82009 7.65734 9.47135 7.46877C10.1226 7.2802 11.0882 6.86306 11.1745 5.98307C11.2608 5.10308 10.7205 4.68022 10.3096 4.43832C9.89868 4.19642 9.17345 3.92975 8.0702 3.89737C6.96695 3.86499 6.43485 3.94118 5.69524 4.10689C4.95563 4.27261 4.0907 4.67641 3.89758 4.7526C3.70446 4.82879 3.3778 4.94689 3.15797 4.97927C2.93815 5.01165 2.36495 5.13736 2.07938 4.6288C1.79381 4.12023 2.22936 3.53357 2.77995 3.15833C3.33055 2.7831 4.09686 2.36405 5.02137 2.12596C5.94588 1.88787 7.2402 1.61739 9.12004 1.82882C10.9999 2.04025 12.401 2.84405 13.04 3.43262C13.6789 4.02118 13.8864 4.44213 13.983 4.72022C14.0795 4.99832 14.2377 5.5126 14.0549 6.08974C13.872 6.66687 13.5515 7.00592 13.3194 7.30687C13.0872 7.60782 12.9208 7.79449 12.9763 8.38305C13.0317 8.97162 13.722 9.89733 14.0672 10.4973C14.4123 11.0973 14.4226 11.5506 13.9706 11.8649C13.5186 12.1792 12.7277 11.8878 12.3188 11.5735C11.91 11.2592 11.1807 10.6021 10.4102 10.0954C9.63982 9.58876 8.63723 9.58495 8.24894 9.59447C7.86065 9.604 6.79849 9.76019 5.73016 9.4859C4.66184 9.21162 4.16877 8.73924 3.92224 8.22877C3.6757 7.7183 3.76404 7.26687 3.82157 6.98497C3.87909 6.70306 4.15028 6.72211 4.21397 6.72783C4.27766 6.73354 4.56939 6.80211 4.51803 7.09925C4.46667 7.39639 4.39682 7.74496 4.70088 8.15639C5.00494 8.56781 5.55759 8.81162 6.08353 8.90495C6.60948 8.99829 7.03064 9.004 7.66136 8.97353C8.29208 8.94305 9.1488 8.87638 9.92333 9.15067C10.6979 9.42495 11.0471 9.67257 11.4375 10.0002C11.8278 10.3278 11.9162 10.404 12.4483 10.8154C12.9804 11.2268 13.3995 11.4478 13.5433 11.2687C13.6871 11.0897 13.4262 10.7164 13.3707 10.6211C13.3153 10.5259 13.2783 10.524 12.9208 9.9659C12.5633 9.40781 12.2305 8.804 12.2531 8.12401C12.2757 7.44401 12.701 6.98687 12.7996 6.86306C12.8785 6.76402 12.9708 6.64529 13.0071 6.5983L13.153 6.38878C13.2584 6.24148 13.4578 5.83831 13.4118 5.40403C13.3543 4.86117 13.1201 4.37737 12.325 3.72595C11.5299 3.07452 10.2192 2.63072 9.19811 2.5031C8.17704 2.37548 6.91765 2.34501 5.61717 2.6631C4.31669 2.98119 3.70241 3.37928 3.43327 3.53357C3.16414 3.68785 2.66079 4.08975 2.81899 4.25927C2.97718 4.4288 3.26481 4.27451 3.77021 4.07261C4.2756 3.87071 4.781 3.49166 6.35678 3.29928C7.93255 3.1069 10.3548 3.2269 11.308 4.35451C12.2613 5.48212 11.9696 6.42116 11.532 6.96021C11.0944 7.49925 10.3774 7.9583 9.34192 8.17925C8.30647 8.4002 7.32032 8.41162 6.52113 8.27067C5.72195 8.12972 5.41172 7.71829 5.37063 7.3602C5.32954 7.00211 5.50006 6.54307 6.24173 6.21926C6.98339 5.89545 7.80929 5.95069 8.46877 5.95831C9.12825 5.96593 9.25563 5.81164 9.24947 5.69164C9.24331 5.57165 9.06456 5.37927 8.37015 5.3545C7.67574 5.32974 7.27512 5.34879 6.77794 5.44784C6.28076 5.54688 6.12873 5.5526 5.34187 5.86498C4.55501 6.17735 4.16055 6.35069 3.6983 6.50307C3.23604 6.65545 2.48616 6.84402 1.71779 6.73925C0.949423 6.63449 0.600166 6.3164 0.328974 5.90307C0.126484 5.59445 -0.188748 5.05545 0.148181 3.93166C0.48511 2.80786 1.49797 1.81496 2.50876 1.2593C3.61407 0.65169 4.79538 0.193948 6.86217 0.036454C8.51191 -0.0892593 9.80623 0.124072 11.0615 0.463117C12.3168 0.802162 14.0138 1.71644 14.9424 2.84405C15.8652 3.96461 15.9501 4.94221 15.9713 5.18618L15.9717 5.1907C15.9922 5.42688 16.1073 6.00212 15.6594 6.9564C15.3011 7.71982 14.765 8.26115 14.5418 8.43639L14.361 8.59067C14.3267 8.61924 14.2406 8.68667 14.1699 8.72781C14.0816 8.77924 13.8597 8.82495 13.7056 8.61543Z", fill: "#303030" }) 
            ),
            wp.element.createElement("div", { style: { marginLeft: "4px" } }, "Originality.ai score"),
            // wp.element.createElement("span", { dangerouslySetInnerHTML: {__html: spanObj.text}, style: {color: spanObj.color, width: "100%", marginTop: "8px", fontSize: "12px", fontWeight: "400", display: (isPanelOpen || !spanObj.text) ? "none" : "block"} }), // AI Content Scan
            wp.element.createElement(
                "div",
                {
                    style: { 
                        fontSize: "12px",
                        fontWeight: "400",
                        display: (isPanelOpen) ? "none" : "flex"
                    },
                    class: "originalityai-header-links",
                },
                wp.element.createElement(
                    "span", 
                    {
                        dangerouslySetInnerHTML: {
                            __html: spanObj.text
                        },
                        style: {
                            color: spanObj.color,
                            display: (spanObj.text) ? "inline-block" : "none"
                        }
                    }),
                wp.element.createElement(
                    "span",
                    {
                        style: {
                            display: (scanResults) ? 'inline-block' : 'none',
                        }
                    },
                    "|"
                ),
                wp.element.createElement(
                    "a",
                    {
                        onClick: (event) => { event.stopPropagation() },
                        target: "_blank",
                        href: scanLink,
                        class: "originalityai-header-links__item",
                        style: {
                            display: (scanResults) ? 'inline-block' : 'none',
                        }
                    },
                    "View Scan"
                ),
                wp.element.createElement(
                    "span",
                    {
                        style: {
                            display: (scanResults) ? 'inline-block' : 'none',
                        }
                    },
                    "|"
                ),
                wp.element.createElement(
                    "a",
                    {
                        onClick: (event) => {
                            event.stopPropagation();
                            scanContent(setIsScanInProgress, selectedValue, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, spanObj, setSeries, setItemColor, setLabelColor);
                        },
                        href: '#',
                        class: `originalityai-header-links__item ${isScanInProgress ? 'loading' : ''}`,
                    },
                    scanText
                ),
            )


        );

        return wp.element.createElement(
            PluginDocumentSettingPanel,
            { name: pluginSettingPanelName, title: titleWithLogo },
            wp.element.createElement(ContentScan, { spanObj, setSpanObj, scanLink, setScanLink, isScanInProgress, setIsScanInProgress, selectedValue, setSelectedValue, models, setModels, scanResults, setScanResults, publicLink, setPublicLink, series, setSeries, itemColor, setItemColor, labelColor, setLabelColor, chart, setChart })
        );
    },
});

function ContentScan({ spanObj, setSpanObj, scanLink, setScanLink, isScanInProgress, setIsScanInProgress, selectedValue, setSelectedValue, models, setModels, scanResults, setScanResults, publicLink, setPublicLink, series, setSeries, itemColor, setItemColor, labelColor, setLabelColor, chart, setChart }) {

    const buttonText = (!scanResults || isScanInProgress) ? "Start New Scan" : 'Click to scan again';

    useEffect(() => {
        const postId = wp.data.select("core/editor").getCurrentPostId();
        getLatestScanResults(setIsScanInProgress, postId, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, false, spanObj, setSeries, setItemColor, setLabelColor);

        if (chart) {
            chart.destroy();
        }

        createCircleChart([series], itemColor, labelColor, setChart);
    }, [scanLink]);


    const scanButton = wp.element.createElement(
        "button",
        {
            onClick: (event) => {
                scanContent(setIsScanInProgress, selectedValue, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, spanObj, setSeries, setItemColor, setLabelColor);
            },
            id: "originalityai__scan-button",
            type: "button",
            class: "components-button originality-ai-sidebar-button",
            disabled: isScanInProgress ? true : false,
        },
        wp.element.createElement("svg", { width: "16", height: "16", viewBox: "0 0 16 16", fill: "none", display: (!scanResults || isScanInProgress) ? 'none' : 'block' }, 
            wp.element.createElement("path", { d: "M7.99984 13.3332C6.51095 13.3332 5.24984 12.8165 4.2165 11.7832C3.18317 10.7498 2.6665 9.48873 2.6665 7.99984C2.6665 6.51095 3.18317 5.24984 4.2165 4.2165C5.24984 3.18317 6.51095 2.6665 7.99984 2.6665C8.7665 2.6665 9.49984 2.82484 10.1998 3.1415C10.8998 3.45817 11.4998 3.91095 11.9998 4.49984V3.33317C11.9998 3.14428 12.0637 2.98595 12.1915 2.85817C12.3193 2.73039 12.4776 2.6665 12.6665 2.6665C12.8554 2.6665 13.0137 2.73039 13.1415 2.85817C13.2693 2.98595 13.3332 3.14428 13.3332 3.33317V6.6665C13.3332 6.85539 13.2693 7.01373 13.1415 7.1415C13.0137 7.26928 12.8554 7.33317 12.6665 7.33317H9.33317C9.14428 7.33317 8.98595 7.26928 8.85817 7.1415C8.73039 7.01373 8.6665 6.85539 8.6665 6.6665C8.6665 6.47761 8.73039 6.31928 8.85817 6.1915C8.98595 6.06373 9.14428 5.99984 9.33317 5.99984H11.4665C11.1109 5.37761 10.6248 4.88873 10.0082 4.53317C9.3915 4.17761 8.72206 3.99984 7.99984 3.99984C6.88873 3.99984 5.94428 4.38873 5.1665 5.1665C4.38873 5.94428 3.99984 6.88873 3.99984 7.99984C3.99984 9.11095 4.38873 10.0554 5.1665 10.8332C5.94428 11.6109 6.88873 11.9998 7.99984 11.9998C8.75539 11.9998 9.44706 11.8082 10.0748 11.4248C10.7026 11.0415 11.1887 10.5276 11.5332 9.88317C11.6221 9.72761 11.7471 9.61928 11.9082 9.55817C12.0693 9.49706 12.2332 9.49428 12.3998 9.54984C12.5776 9.60539 12.7054 9.72206 12.7832 9.89984C12.8609 10.0776 12.8554 10.2443 12.7665 10.3998C12.3109 11.2887 11.6609 11.9998 10.8165 12.5332C9.97206 13.0665 9.03317 13.3332 7.99984 13.3332Z", fill: "#5F5F5F" }) 
        ),
        isScanInProgress ? "Scanning..." : buttonText
    );

    const scanResultsElem = scanResults
        ? wp.element.createElement('div', { dangerouslySetInnerHTML: { __html: scanResults } })
        : 'No scans were performed yet for this post.';

    const resultDiv = wp.element.createElement(
        "div",
        { id: "aiScanResultsDiv", style: { paddingBottom: '8px' } },
        scanResultsElem
    );



    const [linkCopied, setLinkCopied] = useState(false);

    const copyLink = (event, url) => {
        event.preventDefault();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(() => {
                    console.log(url, 'Copied');
                    setLinkCopied(true);
                    setTimeout(function () {
                        setLinkCopied(false);
                    }, 2000);
                })
                .catch(err => {
                    console.error('Copied', err);
                });
        } else {
            fallbackCopyToClipboard(url); // for non-HTTPS
        }


    }

    const links_wrap = wp.element.createElement(
        "div",
        {
            class: "originalityai__scan-links",
        },
        wp.element.createElement(
            "a",
            {
                ...(linkCopied && { 'data-originalityai-tooltip-click': 'Copied' }),
                onClick: (event) => copyLink(event, publicLink),
                href: publicLink,
                class: "originalityai__scan-link-item",
            },
            wp.element.createElement("svg", { width: "16", height: "16", viewBox: "0 0 16 16", fill: "none" }, 
                wp.element.createElement("path", { d: "M6 12.0002C5.63333 12.0002 5.31944 11.8696 5.05833 11.6085C4.79722 11.3474 4.66666 11.0335 4.66666 10.6668L4.66667 2.66683C4.66667 2.30016 4.79722 1.98627 5.05833 1.72516C5.31944 1.46405 5.63333 1.3335 6 1.3335L12 1.3335C12.3667 1.3335 12.6806 1.46405 12.9417 1.72516C13.2028 1.98628 13.3333 2.30016 13.3333 2.66683L13.3333 10.6668C13.3333 11.0335 13.2028 11.3474 12.9417 11.6085C12.6806 11.8696 12.3667 12.0002 12 12.0002L6 12.0002ZM6 10.6668L12 10.6668L12 2.66683L6 2.66683L6 10.6668ZM3.33333 14.6668C2.96666 14.6668 2.65278 14.5363 2.39166 14.2752C2.13055 14.0141 2 13.7002 2 13.3335L2 4.66683C2 4.47794 2.06389 4.31961 2.19167 4.19183C2.31944 4.06405 2.47778 4.00016 2.66667 4.00016C2.85556 4.00016 3.01389 4.06405 3.14167 4.19183C3.26944 4.31961 3.33333 4.47794 3.33333 4.66683L3.33333 13.3335L10 13.3335C10.1889 13.3335 10.3472 13.3974 10.475 13.5252C10.6028 13.6529 10.6667 13.8113 10.6667 14.0002C10.6667 14.1891 10.6028 14.3474 10.475 14.4752C10.3472 14.6029 10.1889 14.6668 10 14.6668L3.33333 14.6668Z", fill: "#156FB9" }) 
            ),
            "Copy link of results"
        ),
        wp.element.createElement(
            "a",
            {
                target: "_blank",
                href: scanLink,
                class: "originalityai__scan-link-item",
            },
            wp.element.createElement("svg", { width: "16", height: "16", viewBox: "0 0 16 16", fill: "none" }, 
                wp.element.createElement("path", { d: "M11.3335 14.6668C10.7779 14.6668 10.3057 14.4724 9.91683 14.0835C9.52794 13.6946 9.33349 13.2224 9.33349 12.6668C9.33349 12.6002 9.35016 12.4446 9.38349 12.2002L4.70016 9.46683C4.52238 9.6335 4.31683 9.76405 4.08349 9.8585C3.85016 9.95294 3.60016 10.0002 3.33349 10.0002C2.77794 10.0002 2.30572 9.80572 1.91683 9.41683C1.52794 9.02794 1.33349 8.55572 1.33349 8.00016C1.3335 7.44461 1.52794 6.97239 1.91683 6.5835C2.30572 6.19461 2.77794 6.00016 3.3335 6.00016C3.60016 6.00016 3.85016 6.04739 4.0835 6.14183C4.31683 6.23627 4.52238 6.36683 4.70016 6.5335L9.3835 3.80016C9.36127 3.72239 9.34739 3.64739 9.34183 3.57516C9.33627 3.50294 9.3335 3.42239 9.3335 3.3335C9.3335 2.77794 9.52794 2.30572 9.91683 1.91683C10.3057 1.52794 10.7779 1.3335 11.3335 1.3335C11.8891 1.3335 12.3613 1.52794 12.7502 1.91683C13.1391 2.30572 13.3335 2.77794 13.3335 3.3335C13.3335 3.88905 13.1391 4.36128 12.7502 4.75016C12.3613 5.13905 11.8891 5.3335 11.3335 5.3335C11.0668 5.3335 10.8168 5.28628 10.5835 5.19183C10.3502 5.09739 10.1446 4.96683 9.96683 4.80016L5.2835 7.5335C5.30572 7.61127 5.31961 7.68627 5.32516 7.7585C5.33072 7.83072 5.33349 7.91128 5.33349 8.00016C5.33349 8.08905 5.33072 8.16961 5.32516 8.24183C5.31961 8.31405 5.30572 8.38905 5.2835 8.46683L9.96683 11.2002C10.1446 11.0335 10.3502 10.9029 10.5835 10.8085C10.8168 10.7141 11.0668 10.6668 11.3335 10.6668C11.8891 10.6668 12.3613 10.8613 12.7502 11.2502C13.1391 11.6391 13.3335 12.1113 13.3335 12.6668C13.3335 13.2224 13.1391 13.6946 12.7502 14.0835C12.3613 14.4724 11.8891 14.6668 11.3335 14.6668ZM11.3335 13.3335C11.5224 13.3335 11.6807 13.2696 11.8085 13.1418C11.9363 13.0141 12.0002 12.8557 12.0002 12.6668C12.0002 12.4779 11.9363 12.3196 11.8085 12.1918C11.6807 12.0641 11.5224 12.0002 11.3335 12.0002C11.1446 12.0002 10.9863 12.0641 10.8585 12.1918C10.7307 12.3196 10.6668 12.4779 10.6668 12.6668C10.6668 12.8557 10.7307 13.0141 10.8585 13.1418C10.9863 13.2696 11.1446 13.3335 11.3335 13.3335ZM3.33349 8.66683C3.52238 8.66683 3.68072 8.60294 3.80849 8.47516C3.93627 8.34739 4.00016 8.18905 4.00016 8.00016C4.00016 7.81127 3.93627 7.65294 3.8085 7.52516C3.68072 7.39739 3.52238 7.3335 3.3335 7.3335C3.14461 7.3335 2.98627 7.39739 2.8585 7.52516C2.73072 7.65294 2.66683 7.81127 2.66683 8.00016C2.66683 8.18905 2.73072 8.34739 2.85849 8.47516C2.98627 8.60294 3.14461 8.66683 3.33349 8.66683ZM11.3335 4.00016C11.5224 4.00016 11.6807 3.93628 11.8085 3.8085C11.9363 3.68072 12.0002 3.52239 12.0002 3.3335C12.0002 3.14461 11.9363 2.98628 11.8085 2.8585C11.6807 2.73072 11.5224 2.66683 11.3335 2.66683C11.1446 2.66683 10.9863 2.73072 10.8585 2.8585C10.7307 2.98628 10.6668 3.14461 10.6668 3.3335C10.6668 3.52239 10.7307 3.68072 10.8585 3.8085C10.9863 3.93628 11.1446 4.00016 11.3335 4.00016Z", fill: "#156FB9" }) 
            ),
            "View Scan"
        ),
    );

    const links = scanResults ? links_wrap : '';






    const modelSelect = wp.element.createElement(
        SelectControl,
        {
            label: "Model",
            value: selectedValue,
            options: models,
            onChange: (value) => setSelectedValue(value) 
        }
    );

    


    return wp.element.createElement(
        "div",
        null,
        resultDiv,
        modelSelect,
        scanButton,
        links
    );


}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;

    // Avoid showing the textarea element
    textArea.style.position = "fixed";
    textArea.style.left = "-99999px";

    if (document.body) {
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Unable to copy to clipboard:', err);
        }

        document.body.removeChild(textArea);
    }

}

function createCircleChart(series, itemColor, labelColor, setChart) {
    const chartContainer = document.querySelector('#originalityai__donut_chart');

    // Destroy the existing chart instance if it exists.
    if (chartContainer && chartContainer.children.length > 0) {
        while (chartContainer.firstChild) {
            chartContainer.removeChild(chartContainer.firstChild);
        }
    }

    var options = {
        chart: {
            height: 240,
            width: '100%',
            type: 'radialBar',
        },
        series: series,
        plotOptions: {
            radialBar: {
                hollow: {
                    size: '70%',
                },
                dataLabels: {
                    showOn: 'always',
                    name: {
                        offsetY: 5,
                        show: true,
                        color: itemColor,
                        fontSize: '18px',
                        fontWeight: 700
                    },
                    value: {
                        offsetY: "90%",
                        color: '#111',
                        fontSize: '12px',
                        show: false,
                        formatter: function (val) {
                            return val + '% Confidence';
                        }
                    }
                }
            }
        },
        fill: {
            colors: [itemColor]
        },
        stroke: {
            // lineCap: 'round'
        },
        // labels: [ response.data.color_mapping_item.label.split(' ') ],
        labels: [labelColor],
    };


    // setTimeout(function () {
    if (jQuery('#originalityai__donut_chart').length) {
        var chartInst = new ApexCharts(jQuery('#originalityai__donut_chart')[0], options);
        chartInst.render();
        setChart(chartInst);
    }
    // }, 100);
}


const scanContent = function (setIsScanInProgress, selectedValue, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, spanObj, setSeries, setItemColor, setLabelColor) {
    setIsScanInProgress(true);
    const data = {
        'action': 'ai_scan',
        'post_id': wp.data.select("core/editor").getCurrentPostId(),
        'post_content': wp.data.select("core/editor").getEditedPostContent(),
        'scan_nonce' : originalityAISidebar.scan_nonce,
    };
    if (selectedValue) {
        data.originalityai_model = selectedValue;
    }

    jQuery.post(ajaxurl, data, function (response) {
        if (response.success) {

            const postId = wp.data.select("core/editor").getCurrentPostId();

            getLatestScanResults(setIsScanInProgress, postId, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, true, spanObj, setSeries, setItemColor, setLabelColor);

            //setScanResults(response.data.raw);
        } else {
            // we're setting scanResults with error message, it will be escaped
            setScanResults('Scanning failed. Reason: ' + response.data.message);
            setIsScanInProgress(false);
        }
        
    });
};

const getLatestScanResults = async (setIsScanInProgress, postId, setModels, setScanResults, setPublicLink, setScanLink, setSelectedValue, setSpanObj, update = false, spanObj, setSeries, setItemColor, setLabelColor) => {

    if (update || !spanObj.text) {

        const data = {
            'action': 'get_latest_scan_results',
            'post_id': postId,
            'nonce' : originalityAISidebar.nonce
        };

        const response = await jQuery.ajax({
            type: "POST",
            url: originalityAISidebar.ajaxurl,
            data: data,
            dataType: 'json'
        });

        if (response?.data?.models) {
            let modelsArr = [];
            for (let key in response.data.models) {
                if (response.data.models.hasOwnProperty(key)) {
                    modelsArr.push({
                        label: response.data.models[key],
                        value: key
                    });
                }
            }
            setModels(modelsArr);
        }

        if (response.success) {
            setScanResults(response.data.html);
            if (typeof response.data.public_link !== 'undefined') {
                setPublicLink(response.data.public_link);
            }
            if (typeof response.data.id !== 'undefined') {
                setScanLink('https://app.originality.ai/home/content-scan/' + response.data.id);
            }
            if (response.data?.ai_model_version) {
                // console.log(response.data.ai_model_version, 'response.data.ai_model_version');
                setSelectedValue(response.data.ai_model_version);
            }

            setSpanObj(prevState => ({
                ...prevState,
                color: response.data.color_mapping_item.color,
                text: response.data.color_mapping_item.label + ' ' + response.data.percentage + '%'
            }));
            // jQuery('#originalityai__sidebar-panel-title span').css('color', response.data.color_mapping_item.color).text(response.data.color_mapping_item.label + ' ' + response.data.percentage + '%');
            // console.log(jQuery('#originalityai__donut_chart')[0]);

            // console.log(setSeries, 'setSeries');
            setSeries(response.data.percentage);
            setItemColor(response.data.color_mapping_item.color);
            setLabelColor(response.data.color_mapping_item.label);

            setIsScanInProgress(false);
            // createCircleChart([series], itemColor, labelColor);
        } else {
            console.log('Scan failed');
            if (response?.data?.current_model_id) {
                setSelectedValue(response.data.current_model_id);
            }
        }

    } else {
        // createCircleChart([series], itemColor, labelColor);
    }
};


/**
 * There's currently no built-in callback for expansion (toggle) in the Gutenberg PluginDocumentSettingPanel component.
 * However, this can be achieved using a custom hook to simulate the effect of a change in the expansion state.
 */

// jQuery(document).ready(function(){

//     setTimeout(function(){
//         if (jQuery('#originalityai__scan-button:visible').length == 0) {

//             jQuery('#originalityai__sidebar-panel-title').parent().parent().hide();

//             const styleId = 'hide-originalityai-button';
//             if (!jQuery(`#${styleId}`).length){
//                 jQuery('head').append(`<style id="${styleId}">#originalityai__scan-button{ display: none !important; }</style>`);
//             }

//             jQuery('#originalityai__sidebar-panel-title').click();

//             setTimeout(function () {
//                 jQuery('#originalityai__sidebar-panel-title').parent().parent().show();
//                 jQuery('#originalityai__scan-button').show();
//                 jQuery('#originalityai__sidebar-panel-title').click();
//                 jQuery(`#${styleId}`).remove();
//             }, 1);

//         }
//     }, 100);

// });


