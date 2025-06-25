jQuery(document).ready(function($){
    var files = [];

    $('.twmg-tab-nav li').on('click', function(){
        var tab = $(this).data('tab');
        $('.twmg-tab-nav li').removeClass('active');
        $(this).addClass('active');
        $('.twmg-tab-content').removeClass('active');
        $('#twmg-' + tab).addClass('active');
    });

    $('#twmg-dropzone').on('click', function(){
        $('#twmg-file-input').click();
    });

    $('#twmg-file-input').on('change', function(e){
        files = this.files;
        $('#twmg-progress').text(files.length + ' files ready to upload');
    });

    $('#twmg-dropzone').on('dragover', function(e){ e.preventDefault(); });
    $('#twmg-dropzone').on('drop', function(e){
        e.preventDefault();
        files = e.originalEvent.dataTransfer.files;
        $('#twmg-progress').text(files.length + ' files ready to upload');
    });

    $('#twmg-start-upload').on('click', function(){
        var gallery = $('#twmg-gallery-name').val();
        if(!gallery || files.length === 0){
            alert('Gallery name and files required');
            return;
        }
        var formData = new FormData();
        $.each(files, function(i,file){ formData.append('files[]', file); });
        formData.append('gallery', gallery);
        formData.append('action', 'twmg_upload');
        formData.append('nonce', twmg.nonce);
        $.ajax({
            url: twmg.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function(){
                var xhr = $.ajaxSettings.xhr();
                if(xhr.upload){
                    xhr.upload.addEventListener('progress', function(e){
                        if(e.lengthComputable){
                            var pct = Math.round((e.loaded / e.total) * 100);
                            $('#twmg-progress').text(pct + '%');
                        }
                    }, false);
                }
                return xhr;
            },
            success: function(res){
                if(res.success){
                    $('#twmg-message').text(res.data);
                    $('#twmg-progress').text('');
                }else{
                    $('#twmg-message').text(res.data || 'Upload failed');
                }
            }
        });
    });

    function loadGalleries(){
        $.post(twmg.ajax_url, {action:'twmg_list_galleries', nonce:twmg.nonce}, function(res){
            if(res.success){
                var list = $('<ul/>');
                $.each(res.data, function(i,g){
                    var li = $('<li/>').text(g.name).attr('data-slug', g.slug);
                    if(twmg.is_admin){
                        li.append(' <a href="#" class="twmg-del" data-slug="'+g.slug+'">Delete</a> <a href="#" class="twmg-reg" data-slug="'+g.slug+'">Regenerate Link</a>');
                    }
                    list.append(li);
                });
                $('#twmg-gallery-list').html(list);
            }
        });
    }

    $('#twmg-manage').on('click','li',function(){
        var slug = $(this).data('slug');
        $.post(twmg.ajax_url,{action:'twmg_get_gallery',slug:slug,nonce:twmg.nonce},function(res){
            if(res.success){
                var data = res.data;
                var html = '<div class="twmg-banner"><h3>'+data.name+'</h3><p>By: 2 Way Media</p></div>';
                html += '<div class="twmg-gallery-grid">';
                $.each(data.images,function(i,img){
                    html += '<div class="twmg-gallery-item"><img src="'+img.src+'" style="width:100%"/><span class="twmg-like">❤</span><a class="twmg-download" href="'+img.download+'" download>↓</a></div>';
                });
                html += '</div><a class="twmg-download-all" href="#" data-slug="'+slug+'">DOWNLOAD ALL PHOTOS</a>';
                $('#twmg-gallery-list').html(html);
            }
        });
    });

    $(document).on('click','.twmg-download-all',function(e){
        e.preventDefault();
        var slug=$(this).data('slug');
        $.post(twmg.ajax_url,{action:'twmg_get_gallery',slug:slug,nonce:twmg.nonce},function(res){
            if(res.success){
                $.each(res.data.images,function(i,img){
                    var link=document.createElement('a');
                    link.href=img.download;
                    link.download='';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        });
    });

    $(document).on('click','.twmg-del',function(e){
        e.preventDefault();
        var slug=$(this).data('slug');
        if(!confirm('Delete gallery?')) return;
        $.post(twmg.ajax_url,{action:'twmg_delete_gallery',slug:slug,nonce:twmg.nonce},function(res){
            alert(res.data);
            loadGalleries();
        });
    });

    $(document).on('click','.twmg-reg',function(e){
        e.preventDefault();
        var slug=$(this).data('slug');
        $.post(twmg.ajax_url,{action:'twmg_regenerate_link',slug:slug,nonce:twmg.nonce},function(res){
            alert(res.data);
        });
    });

    $(document).on('click','.twmg-like',function(){
        var count=parseInt($(this).data('count')||0)+1;
        $(this).data('count',count).text('❤ '+count);
    });

    loadGalleries();
});
