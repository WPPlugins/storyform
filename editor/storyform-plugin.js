(function(scope) {
    tinymce.create('tinymce.plugins.Storyform', {
        /**
         * Initializes the plugin, this will be executed after the plugin has been created.
         * This call is done before the editor instance has finished it's initialization so use the onInit event
         * of the editor instance to intercept that event.
         *
         * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
         * @param {string} url Absolute URL to where the plugin is located.
         */
        init : function(editor, url) {
            var that = this;
            this.editor = editor;

            // Add a button to mark pullquotes with span pullquote class
            editor.addButton('pullquote', {
                title : 'Pullquote',
                cmd : 'pullquote',
                //image : url + '/storyform-pullquote.png'
            });

            editor.addCommand('pullquote', function() {
                editor.formatter.register('pullquote', {inline : 'span', classes: 'pullquote' });
                editor.focus();
                editor.formatter.toggle('pullquote');
                editor.nodeChanged();
            });

            // Add a button to insert page breaks before an element
            editor.addButton('break-before-page', {
                title : 'Toggle "Page Break" before',
                cmd : 'break-before-page',
                onPostRender: function() {
                    var ctrl = this;

                    editor.on('nodechange', function() {
                        var blocks = editor.selection.getSelectedBlocks();
                        var node = blocks[0];
                        var attr = editor.dom.getAttrib(node, 'data-break-before');
                        var active = (attr === 'page');
                        ctrl.active(active);
                    });
                }
            });

            editor.addCommand('break-before-page', function() {
                var blocks = editor.selection.getSelectedBlocks();
                var node = blocks[0];
                var attr = editor.dom.getAttrib(node, 'data-break-before');
                if( attr === 'page' ){
                    node.removeAttribute('data-break-before');
                    if((/^(IMG|PICTURE|IFRAME|FIGURE)$/).test(node.nodeName)) {
                        // Remove on parents as well
                        while(node.parentNode && node.parentNode.nodeName !== 'BODY'){
                            if((/^(P)$/).test(node.parentNode.nodeName) ) {
                                node.parentNode.removeAttribute('data-break-before');
                            }
                            node = node.parentNode;
                        }
                    }
                } else {
                    if((/^(IMG|PICTURE)$/).test(node.nodeName) && node.parentNode.nodeName === 'BODY') {
                        // We can't add :before to img and picture so we must place it in a paragraph and add it there
                        var p = document.createElement('p');
                        node.parentNode.insertBefore(p, node);
                        p.appendChild(node);
                        editor.dom.setAttrib(p, 'data-break-before', 'page');            
                    } 
                    editor.dom.setAttrib(node, 'data-break-before', 'page');        
                    
                }
                editor.nodeChanged();
            });

            editor.on("wpview-selected", function(view){
                var attr = view.getAttribute('data-wpview-type');
                if(attr === 'video' || attr === 'embed' || attr === 'embedURL'){
                    that.addVideoToolbar( view );
                }
            });

            editor.on( 'preprocess', function(e){
                // Cleanup our data-decorational attributes placed on the parent p tag
                var parents = e.node.querySelectorAll('p[data-decorational]');
                [].forEach.call(parents, function(parent){
                    parent.removeAttribute('data-decorational');
                });
            });

            // Ensure we are in sync between child and parent on data-decorational, data-break-before
            editor.on( 'loadcontent', function(){
                that._setInitialLayoutType();
                that._syncData();
            });

            // Show overlay button on click of img, popup on click of button, remove button otherwise
            editor.on( 'mouseup', function( event ) {
                var node = event.target,
                    dom = editor.dom;

                var imageParent = dom.getParent( node, '#storyform-image-toolbar' );
                var videoParent = dom.getParent( node, '#storyform-video-toolbar' );

                if ( node.nodeName === 'DIV' &&  imageParent ) {

                    // Clicked on overlay icon on image
                    if ( dom.hasClass( node, 'storyform-add-overlay' ) ) {
                        that.showPopup( imageParent._node );
                    }

                    // Clicked on pin icon on image
                    if ( dom.hasClass( node, 'storyform-pin-media' ) ) {
                        that.toggleImagePinned( imageParent._node, node );
                    }

                } else if ( node.nodeName === 'IMG' 
                    && ! that.isPlaceholder( node ) 
                ) {
                    // Clicked on image, not on icon
                    that.addImageToolbar( node );

                } else if ( node.nodeName === 'DIV' && videoParent ) {

                    // Clicked on pin icon on video
                    if ( dom.hasClass( node, 'storyform-pin-media' ) ) {
                        that.toggleVideoPinned( videoParent._node, node );
                    }
                } 
                else {
                    // Clicked off a media element
                    that.removeToolbars();

                } 
            });

            
            editor.on( 'init', function() {
                var dom = editor.dom;

                // Remove toolbar to avoid an orphaned toolbar when dragging an image to a new location
                dom.bind( editor.getDoc(), 'dragstart', function( event ) {
                    that.removeToolbars();
                });
            });

            // Remove toolbar on change of position of the img
            editor.on( 'BeforeExecCommand', function( event ) {
                var cmd = event.command
                if ( cmd === 'JustifyLeft' || cmd === 'JustifyRight' || cmd === 'JustifyCenter' ) {
                    that.removeToolbars();
                }
            });

            // Remove toolbar when deleting photo
            editor.on( 'keydown', function( event ) {
                var keyCode = event.keyCode;
                if ( keyCode === tinymce.util.VK.DELETE || keyCode === tinymce.util.VK.BACKSPACE ) {
                    that.removeToolbars();
                }

                if ( !( event.ctrlKey || event.metaKey || event.altKey ||
                    ( keyCode < 48 && keyCode > 90 ) || keyCode > 186 ) ) {
                    that.removeToolbars();

                }
            });

            // 2 seconds after no new node change event make sure we sync the data-decorational, data-break-before to the 
            // parent so we can display the overlay (important when user does "Add media")
            var timeout;
            editor.on( 'nodechange', function ( event ) {
                clearTimeout(timeout);
                timeout = setTimeout(that._syncData.bind(that), 2000);
            });

            editor.on( 'mousedown', function( event ) {
                if ( ! editor.dom.getParent( event.target, '#storyform-image-toolbar' ) 
                    && ! editor.dom.getParent( event.target, '#storyform-video-toolbar' ) ) {
                    that.removeToolbars();
                }
            });

            editor.on( 'cut', function() {
                that.removeToolbars();
            });
        },

        _syncData: function(){
            var dom = this.editor.dom;
            var that = this;

            var imgs = dom.select( 'img:not([data-mce-bogus]), figure:not([data-mce-bogus]), picture:not([data-mce-bogus]), video:not([data-mce-bogus]), iframe:not([data-mce-bogus])' );
            imgs.forEach(function(img){
                // Make sure unspecified data-decorational on freeflow get specified
                if(!img.getAttribute('data-decorational') && that._layoutType === 'freeflow'){
                    img.setAttribute('data-decorational', 'article');
                }

                // Sync data-decorational to parent since we can't use :before on img, video elements
                if(img.getAttribute('data-decorational') !== img.parentNode.getAttribute('data-decorational')) {
                    img.parentNode.setAttribute('data-decorational', img.getAttribute('data-decorational'));
                }


            });

            var nodes = dom.select( '[data-wpview-type="video"], [data-wpview-type="embed"], [data-wpview-type="embedURL"]');
            nodes.forEach(function(node){
                // Move all embedURL's to embed so we can add attributes
                if(dom.getAttrib(node, 'data-wpview-type') === 'embedURL'){
                    that._setWPViewToEmbed(node);
                }

                var value = that._getAttributeValueFromWPViewNode(node);
                if(that._isAttributeDecorational(value)) {
                    dom.setAttrib(node, 'data-decorational', value);
                }
            });

            // Sync break to media since it gets added to parent paragraph
            var nodes = dom.select( '[data-break-before="page"] img:first-child, [data-break-before="page"] figure:first-child, [data-break-before="page"] picture:first-child, [data-break-before="page"] video:first-child, [data-break-before="page"] iframe:first-child');
            nodes.forEach(function(node){
                if(dom.getAttrib(node, 'data-decorational') === 'article'){
                    // Decorational doesn't support break before on media, so let's remove it from all parents.
                    while(node.parentNode && node.parentNode.nodeName !== 'BODY'){
                        dom.setAttrib(node.parentNode, 'data-break-before', null);    
                        node = node.parentNode;
                    }
                } else {
                    dom.setAttrib(node, 'data-break-before', 'page');    
                }
            });
        },

        // Show popup for the attachment to edit overlay text areas
        showPopup: function(imageNode){
            var dom = this.editor.dom;
            classes = tinymce.explode( imageNode.className, ' ' );

            var attachmentId;
            tinymce.each( classes, function( name ) {
                if ( /^wp-image/.test( name ) ) {
                    attachmentId = parseInt( name.replace( 'wp-image-', '' ), 10 );
                }
            });
            // Attachment id might be empty, such as an image inserted from a URL
            var that = this;
            storyform.showPopupForAttachment( attachmentId, imageNode.src, dom.getAttrib( imageNode, 'data-text-overlay' ), function( areas ){
                that.insertOverlayAreasIntoEditor( imageNode, areas );
            });    
        },

        _isAttributeDecorational: function(attribute){
            if(attribute === 'article'){
                return true;
            } else if (attribute === 'pinned'){
                return false;
            }
            var layout = this._layoutType;
            if(layout === 'freeflow'){
                return true;
            } 
            return false;
        },

        _isElementDecorational: function(node){
            return this._isAttributeDecorational(node.getAttribute('data-decorational'));
        },

        _textRegex: /\sdata\-decorational(?:=[\'\"]([^\'\"]*)[\'\"])?/i,

        _getTextFromWPView: function(node){
            var dom = this.editor.dom;
            var text = dom.getAttrib( node, 'data-wpview-text');
            if( !text ){
                return false;
            }
            return decodeURIComponent(text);
        },

        _getAttributeValueFromWPViewNode: function(node){
            var text = this._getTextFromWPView(node);
            if(!text){
                return false;
            }
            var matches = this._textRegex.exec(text);
            return matches && matches[1];
        },

        _wpViewIsDecorational: function(node){
            return this._isAttributeDecorational(this._getAttributeValueFromWPViewNode(node));
        },

        _setWPView: function(node, value){
            var dom = this.editor.dom;
            dom.setAttrib(node, 'data-decorational', value);
            var text = this._getTextFromWPView(node);

            // Clear out the attribute
            text = text.replace(this._textRegex, ''); 
            text = text.replace(/(\[video|\[embed)(\s|\])/i, '$1 data-decorational="' + value + '"$2');
            dom.setAttrib( node, 'data-wpview-text', encodeURIComponent(text) );
            this.editor.nodeChanged();
        },

        _setWPViewToEmbed: function(node){
            var dom = this.editor.dom;
            dom.setAttrib(node, 'data-wpview-type', 'embed');
            var text = this._getTextFromWPView(node);
            text = text.replace(/^\<p\>(.*)\<\/p\>/i, '$1').trim();
            text = '[embed]' + text + '[/embed]';
            dom.setAttrib( node, 'data-wpview-text', encodeURIComponent(text) );
        },

        toggleVideoPinned: function(node, iconNode, decorational){
            var dom = this.editor.dom;
            if(dom.getAttrib(node, 'data-wpview-type') === 'embedURL'){
                this._setWPViewToEmbed(node);
            }
            if(this._wpViewIsDecorational(node)){
                dom.addClass( iconNode, 'active');
                this._setWPView( node, 'pinned');
            } else {
                dom.removeClass( iconNode, 'active');
                this._setWPView( node, 'article');
            }
        },

        toggleImagePinned: function(node, iconNode){
            var dom = this.editor.dom;
            if(this._isElementDecorational(node)) {
                dom.addClass( iconNode, 'active');
                node.setAttribute('data-decorational', 'pinned');
                node.parentNode.setAttribute('data-decorational', 'pinned');
            } else {
                dom.removeClass( iconNode, 'active');
                node.setAttribute('data-decorational', 'article');
                node.parentNode.setAttribute('data-decorational', 'article');
            }

            this.editor.nodeChanged();
        },

        // Actually inserts the text overlay data from the popup into the editor
        insertOverlayAreasIntoEditor: function(imageNode, areas){
            var dom = this.editor.dom;
            dom.setAttrib( imageNode, 'data-text-overlay', areas.join(","));
        },

        // Avoid standard MCE placeholders
        isPlaceholder: function( node ) {
            var dom = this.editor.dom;

            if ( dom.hasClass( node, 'mceItem' ) || dom.getAttrib( node, 'data-mce-placeholder' ) ||
                dom.getAttrib( node, 'data-mce-object' ) ) {

                return true;
            }
            return false;
        },

        _toolbar: null,

        // Display toolbar over the top of the img
        addImageToolbar: function( node ) {
            var rectangle, toolbarHtml, toolbar, left, 
                additionalClass = '',
                dom = this.editor.dom;

            this.removeToolbars();

            // Don't add to placeholders or if we already have one
            if ( ! node || node.nodeName !== 'IMG' || this.isPlaceholder( node ) || node._toolbar ) {
                return;
            }

            rectangle = dom.getRect( node );

            additionalClass += this._isElementDecorational(node) ? '' : 'active';

            toolbarHtml = '<div class="dashicons dashicons-tablet storyform-add-overlay" data-mce-bogus="1"></div>\
                <div class="dashicons dashicons-admin-post storyform-pin-media ' + additionalClass + '" data-mce-bogus="1"></div>';

            toolbar = dom.create( 'div', {
                'id': 'storyform-image-toolbar',
                'data-mce-bogus': '1',
                'contenteditable': false
            }, toolbarHtml );

            toolbar._node = node;
            node._toolbar = toolbar;
            this._toolbar = toolbar;
            this.editor.getBody().appendChild( toolbar );
            this.positionToolbar();
        },

        positionToolbar: function(){
            var dom = this.editor.dom;
            var toolbar = this._toolbar;

            if(toolbar){
                // Make sure the position is correct
                if(toolbar._node.parentNode){
                    var rectangle = dom.getRect( toolbar._node );
                    if ( this.editor.rtl ) {
                        left = rectangle.x;
                    } else {
                        left = rectangle.x + rectangle.w - toolbar.clientWidth;
                    }

                    dom.setStyles( toolbar, {
                        top: rectangle.y,
                        left: left
                    });    
                }
                
                // Cleanup toolbar if node is gone
                if(!toolbar._node.parentNode){
                    dom.remove( toolbar );
                    this._toolbar = null;
                    delete toolbar._node._toolbar;
                }
            }
            
        },

        // Display toolbar over the top of the video
        addVideoToolbar: function( node ) {
            var rectangle, toolbarHtml, toolbar, left, 
                additionalClass = '',
                dom = this.editor.dom;

            this.removeToolbars();

            // Don't add to placeholders or re-add
            if ( ! node || this.isPlaceholder( node ) || node._toolbar ) {
                return;
            }

            rectangle = dom.getRect( node );

            additionalClass += this._wpViewIsDecorational( node ) ? '' : 'active';

            toolbarHtml = '<div class="dashicons dashicons-admin-post storyform-pin-media ' + additionalClass + '" data-mce-bogus="1"></div>';

            toolbar = dom.create( 'div', {
                'id': 'storyform-video-toolbar',
                'data-mce-bogus': '1',
                'contenteditable': false
            }, toolbarHtml );

            toolbar._node = node;
            node._toolbar = toolbar;
            this._toolbar = toolbar;
            this.editor.getBody().appendChild( toolbar );
            this.positionToolbar();
        },

        removeToolbars: function(){
            var dom = this.editor.dom;
            var toolbar = this._toolbar;    
            if ( toolbar ) {
                if( toolbar._node.parentNode ){
                    dom.remove( toolbar );    
                }
                
                this._toolbar = null;

                if(toolbar._node._toolbar){
                    delete toolbar._node._toolbar;    
                }
            }
        },

        _setInitialLayoutType: function(){
            var editor = this.editor;
            var dom = editor.dom;
            if(dom.hasClass(editor.getBody(), 'slideshow')) {
                this._layoutType = 'slideshow';
            } else if(dom.hasClass(editor.getBody(), 'ordered')) {
                this._layoutType = 'ordered'
            } else {
                this._layoutType = 'freeflow';    
            }
        },

        _layoutType: 'freeflow',

        setLayoutType: function(type){
            var dom = this.editor.dom;
            if(type === 'slideshow'){
                dom.addClass(this.editor.getBody(), 'slideshow'); 
                dom.removeClass(this.editor.getBody(), 'freeflow'); 
                dom.removeClass(this.editor.getBody(), 'ordered'); 
                this._addBreakBeforeHeaders();
                this._pinAll();   
            } else if(type === 'freeflow'){
                dom.addClass(this.editor.getBody(), 'freeflow'); 
                dom.removeClass(this.editor.getBody(), 'slideshow'); 
                dom.removeClass(this.editor.getBody(), 'ordered'); 
                if(this._layoutType === 'slideshow'){
                    this._removeBreakBeforeHeaders();    
                }
                this._unpinAll();   
            } else {
                dom.addClass(this.editor.getBody(), 'ordered'); 
                dom.removeClass(this.editor.getBody(), 'slideshow'); 
                dom.removeClass(this.editor.getBody(), 'freeflow'); 
                if(this._layoutType === 'slideshow'){
                    this._removeBreakBeforeHeaders();    
                }
                this._pinAll();
            }
            this._layoutType = type;
            
        },

        /**
         *  Checks if this is the first bit or real rendered content in the document.
         */
        _isFirstContentElement: function(node){
            while(node && node.parentNode && node.nodeName !== 'BODY'){
                while(node.previousSibling){
                    if(node.previousSibling.textContent.trim() !== '' || node.previousSibling.src !== undefined){
                        return false;
                    }
                    node = node.previousSibling;
                }
                node = node.parentNode;
            }
            return true;
        },

        _addBreakBeforeHeaders: function(){
            var dom = this.editor.dom;
            var that = this;

            var nodes = dom.select( 'h1, h2, h3' );
            nodes.forEach(function(node){
                // Don't add it to potential Subtitle
                if(!that._isFirstContentElement(node)){
                    dom.setAttrib(node, 'data-break-before', 'page');    
                }
            });

            this.editor.nodeChanged();
        },

        _removeBreakBeforeHeaders: function(){
            var dom = this.editor.dom;
            var that = this;

            var nodes = dom.select( 'h1, h2, h3' );
            nodes.forEach(function(node){
                if(dom.getAttrib(node, 'data-break-before') === 'page'){
                    dom.setAttrib(node, 'data-break-before', null);    
                }
            });

            this.editor.nodeChanged();
        },

        _pinAll: function(){
            var dom = this.editor.dom;
            var that = this;

            var nodes = dom.select( 'img:not([data-mce-bogus]), figure:not([data-mce-bogus]), picture:not([data-mce-bogus]), video:not([data-mce-bogus])' );
            nodes.forEach(function(node){
                dom.setAttrib(node, 'data-decorational', 'pinned');
                dom.setAttrib(node.parentNode, 'data-decorational', 'pinned');
            });

            var nodes = dom.select( '[data-wpview-type="video"], [data-wpview-type="embed"], [data-wpview-type="embedURL"]');
            nodes.forEach(function(node){
                that._setWPView(node, 'pinned');
            });

            this.editor.nodeChanged();
        },

        _unpinAll: function(){
            var dom = this.editor.dom;
            var that = this;

            var nodes = dom.select( 'img:not([data-mce-bogus]), figure:not([data-mce-bogus]), picture:not([data-mce-bogus]), video:not([data-mce-bogus])' );
            nodes.forEach(function(node){
                dom.setAttrib(node, 'data-decorational', 'article');
                dom.setAttrib(node.parentNode, 'data-decorational', 'article');
            });

            var nodes = dom.select( '[data-wpview-type="video"], [data-wpview-type="embed"], [data-wpview-type="embedURL"]');
            nodes.forEach(function(node){
                that._setWPView(node, 'article');
            });

            this.editor.nodeChanged();
        },
 
        /**
         * Returns information about the plugin as a name/value array.
         * The current keys are longname, author, authorurl, infourl and version.
         *
         * @return {Object} Name/value array containing information about the plugin.
         */
        getInfo : function() {
            return {
                longname : 'Storyform Buttons (Pullquotes, edit image text overlay areas, pinned media...)',
                author : 'Storyform',
                authorurl : 'http://storyform.co',
                infourl : 'http://wiki.moxiecode.com/index.php/TinyMCE:Plugins/example',
                version : "0.5"
            };
        }
    });

    // Register plugin
    tinymce.PluginManager.add( 'storyform', tinymce.plugins.Storyform );
})();