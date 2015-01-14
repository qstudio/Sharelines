/*
 * Plugin: Q Sharelines
 * Versio: 0.1
 */

jQuery(document).ready(function($) {

    tinymce.create('tinymce.plugins.q_sharelines', {
        
        init : function(ed, url) {
            
            var t = this;

            t.url = url;
            t.editor = ed;
            //t._createButtons();
            
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
            
            // highlight [shortcode] ##
            ed.on( 'BeforeSetcontent', function( ed ) {
                if ( ed ) {
                    ed.content = t._highlight_open(ed.content);
                    ed.content = t._highlight_close(ed.content);
                }
            });
            
            // remove highlight ##
            ed.on( 'PostProcess', function( ed ) {
                if (ed.get) {
                    ed.content = t._remove_highlight( ed.content );
                }
            });

            // Register buttons - trigger above command when clicked
            // https://icomoon.io/img/icomoon491.png - icons ##
            ed.addButton( 'q_sharelines_button', { title : 'Add Shareline', cmd : 'q_sharelines_insert_shortcode', image: url + '/q_sharelines.png' });
            
        },
        
        _highlight_open : function(co) {
            return co.replace(/\[sharelines([^\]]*)\]/g, function(a,b){
                return '<q class=\"q_sharelines\">';
            });
        },
        
        _highlight_close : function(co) {
            return co.replace(/\[\/sharelines([^\]]*)\]/g, function(a,b){
                return '</q>';
            });
        },
        
        _remove_highlight : function(co) {
            
            // replace all html tags ##
            co = replaceAll( '<q class=\"q_sharelines\">', '[sharelines]', co ) ;
            co = replaceAll( '</q>', '[/sharelines]', co ) ;
            
            return co ;
            
        }
        
    });

    // Register our TinyMCE plugin
    // first parameter is the button ID1
    // second parameter must match the first parameter of the tinymce.create() function above
    tinymce.PluginManager.add( 'q_sharelines_button', tinymce.plugins.q_sharelines );
    
});

function replaceAll(find, replace, str) {
    return str.replace( new RegExp(find, 'g' ), replace );
}