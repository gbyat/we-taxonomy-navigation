(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { InspectorControls } = wp.blockEditor;
    const {
        PanelBody,
        SelectControl,
        ToggleControl,
        TextControl,
        RangeControl,
    } = wp.components;
    const { useSelect } = wp.data;
    const { createElement: el, Fragment } = wp.element;
    const ServerSideRender = wp.serverSideRender;

    const TAXONOMY_BLOCK = 'we-taxonomy-navigation/taxonomy-list';

    const getTaxonomyOptions = (taxonomies) => {
        if (!taxonomies) {
            return [];
        }
        return taxonomies.map((taxonomy) => ({
            label: taxonomy.labels && taxonomy.labels.singular_name ? taxonomy.labels.singular_name : taxonomy.name,
            value: taxonomy.slug,
            hierarchical: taxonomy.hierarchical,
        }));
    };

    const useTaxonomies = () =>
        useSelect((select) => {
            return select('core').getTaxonomies({ per_page: -1 });
        }, []);

    const getCurrentTaxonomy = (options, slug) =>
        options.find((option) => option.value === slug);

    registerBlockType(TAXONOMY_BLOCK, {
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const taxonomies = useTaxonomies();
            const taxonomyOptions = getTaxonomyOptions(taxonomies);
            const currentTaxonomy = getCurrentTaxonomy(taxonomyOptions, attributes.taxonomy);
            const isHierarchical = currentTaxonomy ? currentTaxonomy.hierarchical : false;

            const orderByOptions = [
                { label: __('Name', 'we-taxonomy-navigation'), value: 'name' },
                { label: __('Slug', 'we-taxonomy-navigation'), value: 'slug' },
                { label: __('ID', 'we-taxonomy-navigation'), value: 'id' },
                { label: __('Count', 'we-taxonomy-navigation'), value: 'count' },
                { label: __('Term order', 'we-taxonomy-navigation'), value: 'term_order' },
            ];

            const displayOptions = [
                { label: __('Unordered list', 'we-taxonomy-navigation'), value: 'ul' },
                { label: __('Ordered list', 'we-taxonomy-navigation'), value: 'ol' },
                { label: __('Dropdown', 'we-taxonomy-navigation'), value: 'dropdown' },
                { label: __('Filterable list', 'we-taxonomy-navigation'), value: 'filterable' },
            ];

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Taxonomy', 'we-taxonomy-navigation'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Taxonomy', 'we-taxonomy-navigation'),
                            value: attributes.taxonomy,
                            options: taxonomyOptions,
                            onChange: (value) => setAttributes({ taxonomy: value }),
                        }),
                        el(SelectControl, {
                            label: __('Display style', 'we-taxonomy-navigation'),
                            value: attributes.displayStyle,
                            options: displayOptions,
                            onChange: (value) => setAttributes({ displayStyle: value }),
                        }),
                        el(ToggleControl, {
                            label: __('Include empty terms', 'we-taxonomy-navigation'),
                            checked: attributes.includeEmpty,
                            onChange: (value) => setAttributes({ includeEmpty: value }),
                        }),
                        el(ToggleControl, {
                            label: __('Show term counts', 'we-taxonomy-navigation'),
                            checked: attributes.showCount,
                            onChange: (value) => setAttributes({ showCount: value }),
                        }),
                        el(ToggleControl, {
                            label: __('Show hierarchy', 'we-taxonomy-navigation'),
                            checked: attributes.showHierarchy,
                            disabled: !isHierarchical,
                            onChange: (value) => setAttributes({ showHierarchy: value }),
                        }),
                        isHierarchical &&
                        el(RangeControl, {
                            label: __('Max depth (0 = unlimited)', 'we-taxonomy-navigation'),
                            value: attributes.maxDepth,
                            min: 0,
                            max: 10,
                            onChange: (value) => setAttributes({ maxDepth: value }),
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('Sorting', 'we-taxonomy-navigation'), initialOpen: false },
                        el(SelectControl, {
                            label: __('Order by', 'we-taxonomy-navigation'),
                            value: attributes.orderBy,
                            options: orderByOptions,
                            onChange: (value) => setAttributes({ orderBy: value }),
                        }),
                        el(SelectControl, {
                            label: __('Order', 'we-taxonomy-navigation'),
                            value: attributes.order,
                            options: [
                                { label: __('Ascending', 'we-taxonomy-navigation'), value: 'ASC' },
                                { label: __('Descending', 'we-taxonomy-navigation'), value: 'DESC' },
                            ],
                            onChange: (value) => setAttributes({ order: value }),
                        })
                    ),
                    attributes.displayStyle === 'dropdown' &&
                    el(
                        PanelBody,
                        { title: __('Dropdown', 'we-taxonomy-navigation'), initialOpen: false },
                        el(TextControl, {
                            label: __('Dropdown title', 'we-taxonomy-navigation'),
                            value: attributes.dropdownTitle,
                            onChange: (value) => setAttributes({ dropdownTitle: value }),
                        }),
                        el(TextControl, {
                            label: __('Title link (optional)', 'we-taxonomy-navigation'),
                            value: attributes.dropdownLink,
                            onChange: (value) => setAttributes({ dropdownLink: value }),
                        }),
                        el(ToggleControl, {
                            label: __('Show button', 'we-taxonomy-navigation'),
                            checked: attributes.showDropdownButton,
                            onChange: (value) => setAttributes({ showDropdownButton: value }),
                        })
                    ),
                    attributes.displayStyle === 'filterable' &&
                    el(
                        PanelBody,
                        { title: __('Filter', 'we-taxonomy-navigation'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Enable filter input', 'we-taxonomy-navigation'),
                            checked: attributes.enableFilter,
                            onChange: (value) => setAttributes({ enableFilter: value }),
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: TAXONOMY_BLOCK,
                    attributes,
                })
            );
        },
        save: () => null,
    });
})(window.wp);
