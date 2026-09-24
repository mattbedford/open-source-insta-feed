// This file only runs in the WordPress editor (the "back end" view),
// never on the live site. Its whole job is to let someone using the
// block editor see a preview and adjust the two settings (count, layout).
//
// The ACTUAL rendering on the live site is handled entirely by PHP,
// in Block::render(). This file never touches the real HTML output.

// Pull the bits we need off WordPress's global `wp` object.
// These exist automatically in the editor, no import statements needed,
// because we're not using a build step.
const { registerBlockType } = wp.blocks;
const { useBlockProps, InspectorControls } = wp.blockEditor;
const { PanelBody, RangeControl, SelectControl } = wp.components;
const { createElement: el } = wp.element;

registerBlockType( 'osif/insta-feed', {

    // The "edit" function controls what shows up INSIDE the block editor
    // when someone adds this block to a page. It does NOT control what
    // visitors see on the actual site, that's Block::render()'s job.
    edit: function ( props ) {

        // `attributes` holds the current values (count, layout).
        // `setAttributes` is how we update them when someone changes a setting.
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;

        // useBlockProps() just gives us the standard WordPress wrapper
        // styling/classes for free. Every block editor uses this.
        const blockProps = useBlockProps();

        // el() builds a bit of UI, same idea as writing HTML, just as
        // JavaScript function calls instead of JSX. Reads a bit oddly
        // at first: el( 'tag or component', props, children ).
        return el(
            wp.element.Fragment,
            {},

            // The settings panel on the RIGHT side of the editor,
            // the one you open by clicking the block while it's selected.
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: 'Instagram Feed Settings' },

                    // A slider for how many photos to show.
                    el( RangeControl, {
                        label: 'Number of photos',
                        value: attributes.count,
                        min: 1,
                        max: 20,
                        onChange: function ( newCount ) {
                            setAttributes( { count: newCount } );
                        },
                    } ),

                    // A dropdown for layout style. Add more options here later
                    // if you build more than one CSS layout.
                    el( SelectControl, {
                        label: 'Layout',
                        value: attributes.layout,
                        options: [
                            { label: 'Grid', value: 'grid' },
                            { label: 'List', value: 'list' },
                        ],
                        onChange: function ( newLayout ) {
                            setAttributes( { layout: newLayout } );
                        },
                    } )
                )
            ),

            // What's actually visible in the editor canvas itself.
            // This is just a simple placeholder, it does NOT need to look
            // like the real feed, since Block::render() handles the real
            // thing on the live site. This is purely "so the editor isn't
            // showing a blank empty box".
            el(
                'div',
                blockProps,
                el( 'p', {}, 'Instagram Feed (' + attributes.count + ' photos, ' + attributes.layout + ' layout)' )
            )
        );
    },

    // "save" controls what gets written into the post content in the
    // database. Because this is a DYNAMIC block (rendered fresh by PHP
    // every time, via render_callback), we deliberately save nothing.
    // Returning null tells WordPress "don't save any markup, just
    // remember the attributes and let PHP do the rendering."
    save: function () {
        return null;
    },
} );