(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { InspectorControls, useBlockProps, InnerBlocks } = wp.blockEditor;
    const { PanelBody, SelectControl, ToggleControl, RangeControl } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { createElement: el, Fragment, useEffect } = wp.element;

    const BLOCK_NAME = 'we-taxonomy-navigation/taxonomy-navigation';

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

    const fetchTaxonomyTerms = (taxonomy, attributes) => {
        return wp.apiFetch({
            path: `/we-taxonomy-navigation/v1/terms?taxonomy=${taxonomy}&orderBy=${attributes.orderBy}&order=${attributes.order}&includeEmpty=${attributes.includeEmpty ? '1' : '0'}&showHierarchy=${attributes.showHierarchy ? '1' : '0'}&maxDepth=${attributes.maxDepth}`,
        });
    };

    registerBlockType(BLOCK_NAME, {
        edit: (props) => {
            const { attributes, setAttributes, clientId } = props;
            const taxonomies = useTaxonomies();
            const taxonomyOptions = getTaxonomyOptions(taxonomies);
            const currentTaxonomy = getCurrentTaxonomy(taxonomyOptions, attributes.taxonomy);
            const isHierarchical = currentTaxonomy ? currentTaxonomy.hierarchical : false;
            const blockProps = useBlockProps();
            const { replaceInnerBlocks } = useDispatch('core/block-editor');
            const { getBlock } = useSelect((select) => select('core/block-editor'), []);

            useEffect(() => {
                const currentBlock = getBlock(clientId);
                if (!currentBlock || !currentBlock.innerBlocks || currentBlock.innerBlocks.length === 0) {
                    const navBlock = wp.blocks.createBlock('core/navigation', {});
                    replaceInnerBlocks(clientId, [navBlock], false);
                }
            }, [clientId]);

            useEffect(() => {
                fetchTaxonomyTerms(attributes.taxonomy, attributes).then((links) => {
                    const currentBlock = getBlock(clientId);
                    if (!currentBlock || !currentBlock.innerBlocks || currentBlock.innerBlocks.length === 0) {
                        return;
                    }
                    const navBlock = currentBlock.innerBlocks[0];
                    const linkBlocks = links.map((link) => wp.blocks.createBlock('core/navigation-link', link.attrs));
                    replaceInnerBlocks(navBlock.clientId, linkBlocks, false);

                    // Clear ref attribute
                    if (navBlock.attributes.ref) {
                        wp.data.dispatch('core/block-editor').updateBlockAttributes(navBlock.clientId, { ref: undefined });
                    }
                });
            }, [attributes.taxonomy, attributes.orderBy, attributes.order, attributes.includeEmpty, attributes.showHierarchy, attributes.maxDepth, clientId]);

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
                        el(ToggleControl, {
                            label: __('Include empty terms', 'we-taxonomy-navigation'),
                            checked: attributes.includeEmpty,
                            onChange: (value) => setAttributes({ includeEmpty: value }),
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
                            options: [
                                { label: __('Name', 'we-taxonomy-navigation'), value: 'name' },
                                { label: __('Slug', 'we-taxonomy-navigation'), value: 'slug' },
                                { label: __('ID', 'we-taxonomy-navigation'), value: 'id' },
                                { label: __('Count', 'we-taxonomy-navigation'), value: 'count' },
                                { label: __('Term order', 'we-taxonomy-navigation'), value: 'term_order' },
                            ],
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
                    )
                ),
                el('div', blockProps, el(InnerBlocks, { allowedBlocks: ['core/navigation'], template: [['core/navigation', {}]] }))
            );
        },
        save: () => el(InnerBlocks.Content),
    });
})(window.wp);
