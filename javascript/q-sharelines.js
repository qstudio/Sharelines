/*
 * Plugin: Q Sharelines
 * Versio: 0.1
 */

jQuery(document).ready(function($) {

    tinymce.create('tinymce.plugins.q_sharelines', {
        
        init : function(ed, url) {
            
                // Register command for when button is clicked
                ed.addCommand('q_sharelines_insert_shortcode', function() {
                    
                    selected = tinyMCE.activeEditor.selection.getContent();

                    if( selected ) {
                        
                        //If text is selected when button is clicked
                        //Wrap shortcode around it.
                        content =  '[sharelines]'+selected+'[/sharelines]';
                        
                    } else {
                        
                        content =  '[sharelines]';
                        
                    }

                    tinymce.execCommand( 'mceInsertContent', false, content);
                    
                });

            // Register buttons - trigger above command when clicked
            // https://icomoon.io/img/icomoon491.png - icons ##
            ed.addButton( 'q_sharelines_button', { title : 'Add Shareline', cmd : 'q_sharelines_insert_shortcode', image: url + '/q_sharelines.png' });
            
        }
    });

    // Register our TinyMCE plugin
    // first parameter is the button ID1
    // second parameter must match the first parameter of the tinymce.create() function above
    tinymce.PluginManager.add( 'q_sharelines_button', tinymce.plugins.q_sharelines );
    
});