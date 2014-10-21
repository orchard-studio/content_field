(function($) {
	$(document).ready(function() {
		
				$('.image-data').hide();
				$('.content-field-duplicator').symphonyDuplicator({
					orderable: true,
					collapsible: true,
					constructable: true,
					preselect: $(this).data('preselect')
				});		
				
				$('div.field.field-content').on('change autosize', 'textarea.size-auto', function() {
					var padding = this.offsetHeight - this.clientHeight;
					this.style.height = 'auto';
					this.style.height = (this.scrollHeight + padding) + 'px';
				}).on('cut paste drop keydown', 'textarea.size-auto', function() {
					var $textarea = $(this);
					setTimeout(function() {
						$textarea.trigger('autosize');
					}, 0);
				}).find('textarea.size-auto').trigger('autosize');
				instanceSelector = 'li.instance.content-type-image-upload';
				dropTargetSelector = 'div.drop-target',
				imagePreviewSelector = 'div.image-preview',
				root = Symphony.Context.get('root'),
				baseurl = root + '/symphony/extension/content_field/upload/';							
				function buildDropInterface(instance) {
					var $self = $(dropTargetSelector, instance).bind('dragenter', function(e) {
							$self.addClass('ready-to-drop');
							return false;
						}).bind('dragexit', function(e) {
							$self.removeClass('ready-to-drop');
							return false;
						}).bind('dragover', function(e) {
							return false;
						}).bind('drop', function(e) {
							e.stopPropagation();
							e.preventDefault();
							$self.removeClass('ready-to-drop');
						
							var files = e.originalEvent.dataTransfer.files;
							// No files found:
							var datatrans = e.originalEvent.dataTransfer;
							
							if (files.length == 0) return false;
							var reader = new FileReader(),	file = files.item(0);
							
							
							
								
							// Update interface:
							$self.addClass('uploading').text(file.name);
							// File is ready:
							reader.onload = function(evt) {
								var data = evt.target.result;					
								
								$self.hide();	
								buildImageInterface(instance, data,file);
								console.log({name:file.name,type:file.type,entryid:$('#image-url').attr('data-entry-id'),dataurl:data,imageurl:$('#image-url').attr('data-image')+'/'+file.name});
								
								
							};			
							// Start reading file:
							reader.readAsDataURL(file);				
							return false;
						});

				};

				var buildImageInterface = function(instance, data,file) {
						console.log(instance);
						var $self = $(imagePreviewSelector, instance),
						$image = $self.find('img');
						$image.attr('src', data);
						$.ajax({
							type:'post',
							url: baseurl,
							data:{name:file.name,type:file.type,entryid:$('#image-url').attr('data-entry-id'),dataurl:data,imageurl:$('#image-url').attr('data-image')+'/'+file.name}							
						}).done(function (data) {																	 
								$(instance).find('.image-data').attr('value',data.post);
						});
						$self.show();
				};
				if($('li.content-type-image-upload.instance').length >0){					
					$(instanceSelector).each(function(){
						buildDropInterface(this);						
					});										
				}
				$('div.field-content').on('constructshow.duplicator', instanceSelector, function() {
						buildDropInterface(this);
				});	
				$('.expand').click(function(event){
					event.preventDefault();
					$('li.instance').each(function(){
						if($(this).hasClass('collapsed')){
							$(this).find('header').click();
						}
						
					});
					
				});
				$('.collapse').click(function(event){
					event.preventDefault();
					$('li.instance').each(function(){
						if(!$(this).hasClass('collapsed')){
							$(this).find('header').click();
						}
						
					});
					
				});
				$('.remove-file').click(function(event){
					event.preventDefault();
					$(this).hide();
					$('#image-url').attr('value','');
					$('.image-preview').hide();
					$('.drop-target').show();		
				});
	});
	
})(jQuery);
