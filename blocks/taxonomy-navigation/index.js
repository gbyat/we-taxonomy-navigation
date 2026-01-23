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

            // Helper function to create blocks from link data (supporting submenu)
            const createBlockFromLink = (linkData) => {
                const attrs = { ...linkData.attrs };

                // Ensure className is set for identification
                if (!attrs.className) {
                    attrs.className = '';
                }

                if (linkData.blockName === 'core/navigation-submenu') {
                    // Mark as taxonomy-generated submenu
                    attrs.className = attrs.className ?
                        attrs.className + ' taxonomy-generated-submenu' :
                        'taxonomy-generated-submenu';

                    // Create submenu with children
                    const children = linkData.innerBlocks.map(createBlockFromLink);
                    return wp.blocks.createBlock('core/navigation-submenu', attrs, children);
                }

                // Mark as taxonomy-generated link
                attrs.className = attrs.className ?
                    attrs.className + ' taxonomy-generated-link' :
                    'taxonomy-generated-link';

                // Create simple navigation link
                return wp.blocks.createBlock('core/navigation-link', attrs);
            };

            useEffect(() => {
                fetchTaxonomyTerms(attributes.taxonomy, attributes).then((links) => {
                    const currentBlock = getBlock(clientId);
                    if (!currentBlock || !currentBlock.innerBlocks || currentBlock.innerBlocks.length === 0) {
                        return;
                    }
                    const navBlock = currentBlock.innerBlocks[0];

                    if (!navBlock || navBlock.name !== 'core/navigation') {
                        return;
                    }

                    // Get existing blocks, filter out taxonomy-generated ones
                    const existingBlocks = navBlock.innerBlocks || [];
                    existingBlocks.forEach((block, idx) => {
                    });

                    const userBlocks = existingBlocks.filter((block) => {
                        // Keep all blocks that are NOT taxonomy-generated
                        // This includes: search, custom links, spacers, etc.
                        const className = block.attributes.className || '';
                        const isTaxonomyGenerated = className.includes('taxonomy-generated-link') ||
                            className.includes('taxonomy-generated-submenu');

                        // Also check by kind/type - if it's a taxonomy link, it was generated by us
                        const isTaxonomyLink = block.attributes.kind === 'taxonomy';

                        return !isTaxonomyGenerated && !isTaxonomyLink;
                    });

                    userBlocks.forEach((block) => {
                    });

                    // Create new taxonomy blocks (supporting submenus)
                    const taxonomyBlocks = links.map(createBlockFromLink);

                    // Combine: taxonomy blocks first, then user blocks (like search)
                    const allBlocks = [...taxonomyBlocks, ...userBlocks];

                    replaceInnerBlocks(navBlock.clientId, allBlocks, false);

                    // Always clear ref attribute to prevent using synced navigation
                    if (navBlock.attributes.ref !== undefined) {
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
