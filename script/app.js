/**
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require(['jquery',  'core/modal_factory', 'core/notification', 'core/modal_events'], function ($, ModalFactory, notification, ModalEvents) {
    
    // var index_page = M.cfg.wwwroot + '/local/rollover_wizard/index.php';
    var wizard_step = 1;
    var wizard_mode = null;
    var main_modal = null;
    var wizard_source_courseid = null;
    var wizard_target_courseid = null;
    var wizard_activity_filter_list = null;
    var wizard_selected_activity = [];
    var canceltext = 'Cancel';
    var nexttext = 'Next';
    var wizard_max_step = 10;
    $(document).ready(function(){
        $('.nav-item').on('click', function(e){
            if($(this).data('key') == 'rolloverwizard'){
                wizard_step = 1;
                wizard_source_courseid = null;
                wizard_mode = null;
                var promise = ajax('retrievesessiondata');
                promise.then(function(result){
                    if (result.length != 0) {
                        var result = JSON.parse(result);
                        if(result.status == 200){
                            var data = result.data.data;
                            if(data['source_course']){
                                wizard_source_courseid = data['source_course']['id'];
                            }
                            if(data['target_course']){
                                wizard_target_courseid = data['target_course']['id'];
                            }
                        }
                    }

                    var html_footer = '<div style="margin-left: auto;margin-right:auto;width:90%;">'
                    +'<div class="d-flex justify-content-between align-items-center">'
                    +'    <button type="button" id="wizard_cancel_button" class="btn btn-secondary">'+canceltext+'</button>'
                    +'    <div class="progress" style="min-width: 60%;"><div class="progress-bar progress-bar-striped" role="progressbar" style="width: 0;" id="wizard-progress-bar"></div></div>'
                    +'    <button type="button" id="wizard_next_button" class="btn btn-primary">'+nexttext+'</button>'
                    +'</div>'
                    +'</div>';
                    main_modal = ModalFactory.create({
                        large: true,
                        title: 'Content Rollover Wizard',
                        // type: ModalFactory.types.SAVE_CANCEL,
                        body: '<div class="modal-body-container"></div>',
                        removeOnClose: true,
                        large: true,
                        footer: html_footer,
                        // scrollable: true,
                    }).then(function(modal){
                        var root = modal.getRoot();
                        
                        $(root).find('#wizard_cancel_button').on('click',function(){
                            // wizard_step = 1;
                            if(wizard_step == 1){
                                ajax('savesourcecourseid', {sourcecourseid: -1, mode: wizard_mode})
                                main_modal.destroy();
                            }
                            else{
                                wizard_step--;
                                modalChangeView();
                            }
                        });
                        $(root).find('#wizard_next_button').on('click',function(){
                            processForm();
                        });
                        $(root).find('.modal-dialog').removeClass('modal-lg');
                        $(root).find('.modal-dialog').addClass('modal-xl');
                        main_modal = modal;
                        modalChangeView();
                    });
                });
            }
        });
    });
    function modalChangeView(){
        if(wizard_step == 1){
            var content = '';
            var promise = ajax('renderform', {step: wizard_step});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        var data = result.data;
                        content+=data.html;
                        modalShow(content);
                    }
                }
            });
        }
        if(wizard_step == 2){
            var content = '';
            var promise = ajax('renderform', {step: wizard_step, mode: wizard_mode});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        var data = result.data;
                        content+=data.html;
                        modalShow(content);
                    }
                }
            });
        }
        if(wizard_step == 3){
            var content = '';
            var promise = ajax('renderform', {step: wizard_step, mode: wizard_mode});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        var data = result.data;
                        content+=data.html;
                        wizard_activity_filter_list = data.activity_types;
                        modalShow(content);
                    }
                }
            });
        }
        if(wizard_step == 4){
            var content = '';
            var promise = ajax('renderform', {step: wizard_step, mode: wizard_mode});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        var data = result.data;
                        content+=data.html;
                        modalShow(content);
                    }
                }
            });
        }
        if(wizard_step == 5){
            var content = '';
            var promise = ajax('renderform', {step: wizard_step, mode: wizard_mode});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        var data = result.data;
                        content+=data.html;
                        modalShow(content);
                    }
                }
            });
        }
    }
    function modalShow(content){
        content = mainContainer(content);
        var root = main_modal.getRoot();
        $(root).find('.modal-body-container').html(content);
        $(root).find('.changecoursebutton').on('click', function(){
            modalSourceCourse();
        });
        $(root).find('#btn-select-all').on('click', function(){
            $('input[name="rollover-wizard-cm[]"]').prop('checked', true);
        });
        $(root).find('#btn-deselect-all').on('click', function(){
            $('input[name="rollover-wizard-cm[]"]').prop('checked', false);
        });
        $(root).find('#btn-select-filter').on('click', function(){
            modalFilterActivity();
        });
        var progress_width = 0;
        if(wizard_step > 1){
            progress_width = (wizard_step / wizard_max_step) * 100;
        }
        $(root).find('#wizard-progress-bar').css('width', progress_width+"%");
        main_modal.show();
    }
    function mainContainer(content){
        var html_body = '';
        if(wizard_mode == 'previouscourse' && wizard_step > 1){
            canceltext = 'Back';
        }
        else{
            canceltext = 'Cancel';
        }
        
        if(wizard_step == 4 && wizard_mode == 'previouscourse'){
            nexttext = 'Process';
        }
        else{
            nexttext = 'Next';
        }
        
        $(main_modal.getRoot()).find('#wizard_next_button').text(nexttext);
        $(main_modal.getRoot()).find('#wizard_cancel_button').text(canceltext);
        html_body+='<style type="text/css">'
            +'.wrappermodalcontent > p { cursor: pointer; margin: 0 !important; padding: 5px !important; }'
            +'.wrappermodalcontent > .selected { background: #6fa8dc !important; }'
            +'.collapse-toggle::after {content: ">"; font-weight: bold; float: right; transition: transform 0.3s ease;transform: rotate(-90deg);}'
            +'.collapsed + .collapse-toggle::after {transform: rotate(270deg) !important;}'
            +'.asdasdasda{}'
            +'</style>'
            + '<div class="container" style="padding-top: 15px;padding-left: 5rem;padding-right: 5rem;">'
            +'<div class="main-content">'
            + content
            +'</div>'
            +'</div>'
            +'<div class="spacer" style="min-height:13vh;">&nbsp;</div>';
        return html_body;
    }
    function processForm(){
        var root = main_modal.getRoot();
        if(wizard_step == 1){
            wizard_mode = $(root).find('input[name=content_option]:checked').val();
            if(!wizard_mode || wizard_mode == 'blanktemplate'){
                notification.alert('Info', 'Please select at one option.', 'Ok');
                return;
            }
            if(wizard_mode == 'blanktemplate'){
                wizard_max_step = 4;
            }
            if(wizard_mode == 'previouscourse'){
                wizard_max_step = 5;
            }
            wizard_step++;
            modalChangeView();
            return;
        }
        if(wizard_step == 2){
            if(!wizard_source_courseid){
                notification.alert('Info', 'Please select source course first.', 'Ok');
                return;
            }
            wizard_step++;
            modalChangeView();
            return;
        }
        if(wizard_step == 3){
            wizard_selected_activity = [];
            $('input[name="rollover-wizard-cm[]"]').each(function(index,item){
                var checked = $(this).prop('checked');
                var key = $(this).data('module');
                var section = $(this).data('section');
                var value = $(this).val();
                if(checked){
                    wizard_selected_activity.push({
                        key: key,
                        value: value ,
                        section: section 
                    });
                }
            });
            
            if(wizard_selected_activity.length < 1){
                notification.alert('Info', 'Please select activity type.', 'Ok');
                return;
            }
            var data = JSON.stringify(wizard_selected_activity);
            var promise = ajax('saveselectedactivity', {selectedactivity: data});
            promise.then(function(result){
                if (result.length != 0) {
                    var result = JSON.parse(result);
                    if(result.status == 200){
                        wizard_step++;
                        modalChangeView();
                    }
                }
            });
            return;
        }
        if(wizard_step == 4){
            var rollover_process_mode = $('#rollover_process_mode').val();
            if(rollover_process_mode == 'instantexecute'){
                modalConfirmProcess();
            }
        }
    }
    function ajax(action = '', parameters = {}){
        var base_parameter = {action: action, sesskey: M.cfg.sesskey};
        base_parameter = {...base_parameter, ...parameters};
        return ajaxRequest(base_parameter);
    }
    function ajaxRequest(parameters){
        var deferrer = $.Deferred();
        $.ajax({
            type: "POST",
            url: M.cfg.wwwroot + "/local/rollover_wizard/ajax.php",
            dataType: "html",
            data: parameters,
            beforeSend: function () {
            },
            success: function (res) {
                deferrer.resolve(res);
            },
            error: function (data, res) {
                deferrer.reject(res);
            },
            complete: function () {

            }
        });
        return deferrer;
    }
    function modalFilterActivity(){
        var html_body = '<div class="d-flex flex-column text-left w-75 mx-auto" style="gap:10px;">';
        $.each(wizard_activity_filter_list, function(index, item){
            var random_string = generateRandomString(8);
            var is_checked = false;
            var element_count = 0;
            var selected_count = 0;
            $('.rollover-check-'+item.key).each(function(i, e){
                var selected = $(this).prop('checked');
                if(selected){
                    selected_count++;
                }
                element_count++;
            });
            is_checked = element_count == selected_count;
            var checked = "";
            if(is_checked){
                checked = "checked";
            }
            var element = ''
            +'<div class="form-check">'
                +'<input class="form-check-input rollover-check-filter" data-module="'+item.key+'" type="checkbox" value="" id="rollover-activity-filter'+random_string+'" '+checked+'>'
                +'<label class="form-check-label" for="rollover-activity-filter'+random_string+'">'
                +item.value
                +'</label>'
            +'</div>';
            html_body+=element;
        });
        html_body += '</div>';
        ModalFactory.create({
            large: true,
            title: 'Select activity types',
            body: html_body,
        })
            .then(function (modal) {
                var root = modal.getRoot();
                root.on(ModalEvents.save, function () {
                    //
                });
                $(root).find('.modal-dialog').removeClass('modal-lg');
                $(root).find('.modal-dialog').addClass('modal-sm');

                $(root).find('.rollover-check-filter').on('change', function(evt){
                    var key = $(this).data('module');
                    var checked = $(this).prop('checked');
                    $('.rollover-check-'+key).prop('checked', checked);
                });
                modal.show();
            });
    }
    function modalConfirmProcess(){
        var html_body = '';
        html_body+="<div class='container'>"
        html_body+="<p>The import process will start immediately</p>";
        html_body+="<p>Do you want to proceed ?</p>";
        html_body+="<div class='d-flex justify-content-between'>";
        html_body+="<button type='button' class='btn btn-secondary' id='btn-cancel-process'>Cancel</button>";
        html_body+="<button type='button' class='btn btn-primary' id='btn-confirm-process'>Confirm</button>";
        html_body+="</div>";
        html_body+="</div>";
        ModalFactory.create({
            large: true,
            title: 'Start Import',
            body: html_body,
        })
            .then(function (modal) {
                var root = modal.getRoot();
                root.on(ModalEvents.save, function () {
                    //
                });
                
                $(root).find('#btn-cancel-process').on('click', function(){
                    modal.destroy();
                });
                $(root).find('#btn-confirm-process').on('click', function(){
                    // alert('Confirm');
                    modal.destroy();
                    wizard_step++;
                    modalChangeView();
                });
                $(root).find('.modal-dialog').removeClass('modal-lg');
                $(root).find('.modal-dialog').addClass('modal-sm');
                modal.show();
            });
    }
    // Content Rollover Code
    
    function modalSourceCourse(){
        
        var html_body = '';
        var sourcecourseid = wizard_target_courseid;
        html_body += '<div class="alert-container">';
        html_body += '</div>';
        html_body += '<div class="crsearchbarcontainer">';
        html_body += '</div>';
        html_body += '<div class="wrappermodalcontent" style="min-height: 457px;">';
        html_body += '<span style="font-size: 28px; display: block; margin: 0 auto; text-align: center; padding: 200px 0;"><i class="fa fa-spin fa-spinner"></span>';
        html_body += '</div>';

        ModalFactory.create({
            large: true,
            title: 'Select a source course',
            body: html_body,
        })
            .then(function (modal) {
                var root = modal.getRoot();
                root.on(ModalEvents.save, function () {
                    //
                });
                modal.show();
                var searchbtn = '';
                searchbtn += '<div class="input-group">';
                searchbtn += '<input type="text" class="form-control crsearchbar" placeholder="Search Courses Name...">';
                searchbtn += '<div class="input-group-append">';
                searchbtn += '<button type="button" class="btn btn-primary crsearchbtn"> Search </button>';
                // searchbtn += '<button type="button" class="btn btn-danger crresetbtn"> Reset </button>';
                searchbtn += '<button type="button" class="btn btn-success crsavebtn"> Save </button>';
                searchbtn += '</div>';
                searchbtn += '</div>';
                $(root).find('.crsearchbarcontainer').html(searchbtn);
                $(root).find('.crsavebtn').on('click', function () {
                    $(root).find('button.close').click();
                });
                $(root).find('.crsearchbtn').on('click', function () {
                    $(root).find('.wrappermodalcontent').html('<span style="font-size: 28px; display: block; margin: 0 auto; text-align: center; padding: 200px 0;"><i class="fa fa-spin fa-spinner"></span>');
                    var search = $(root).find('.crsearchbar').val();
                    retrievecourses(0, root, search,sourcecourseid);
                });
                $(root).find(".crsearchbar").on("keydown",function search(e) {
                    if(e.keyCode == 13) {
                        $(root).find('.wrappermodalcontent').html('<span style="font-size: 28px; display: block; margin: 0 auto; text-align: center; padding: 200px 0;"><i class="fa fa-spin fa-spinner"></span>');
                        var search = $(this).val();
                        retrievecourses(0, root, search,sourcecourseid);
                    }
                });
                // $(root).find('.crresetbtn').on('click', function () {
                //     $(root).find('.wrappermodalcontent').html('<span style="font-size: 28px; display: block; margin: 0 auto; text-align: center; padding: 200px 0;"><i class="fa fa-spin fa-spinner"></span>');
                //     $(root).find('.crsearchbar').val('');
                // });
                retrievecourses(0, root, null,sourcecourseid);
            });
    }
    function retrievecourses(categoryid, modalcontent, search = null, sourcecourseid = 0) {
        var data = {};
        if(search == null){
            data = { action: "retrievecourses", categoryid: categoryid, courseid: sourcecourseid, sesskey: M.cfg.sesskey };
        }
        else{
            data = { action: "searchcourses", search: search, courseid: sourcecourseid,sesskey: M.cfg.sesskey }
        }
        $.ajax({
            type: "POST",
            url: M.cfg.wwwroot + "/local/rollover_wizard/ajax.php",
            dataType: "html",
            data: data,
            beforeSend: function () {
            },
            success: function (response) {
                if (response.length != 0) {
                    var html = '';
                    var data = JSON.parse(response);

                    $.each(data['categories'], function (key, value) {
                        html += '<p class="modalcategory" data-categoryid="' + value['id'] + '">'
                            + '<span><i class="icon fa fa-folder-o fa-fw"></i></span><span>'
                            + value['name'] + '</span></p>';
                    });
                    if(data['courses'].length > 0){
                        $.each(data['courses'], function (key, value) {
                            var courseid = value['id'];
                            if(wizard_source_courseid == courseid){
                                html += '<p class="modalcourse selected" data-courseid="' + value['id'] + '" data-url="'+value['urlviewcourse']+'">'
                                + '<span>' + value['fullname'] + '</span></p>';
                            }
                            else{
                                html += '<p class="modalcourse" data-courseid="' + value['id'] + '" data-url="'+value['urlviewcourse']+'">'
                                + '<span>' + value['fullname'] + '</span></p>';
                            }
                        });
                    }
                    else{
                        html += '<p class="text-center w-100">No matching courses found. Please consider refining your search criteria</p>';
                    }

                    $(modalcontent).find('.wrappermodalcontent').html(html);

                    $('.wrappermodalcontent').off().on('click', '.modalcategory', function () {
                        $(modalcontent).find('.wrappermodalcontent').html('<span style="font-size: 28px; display: block; margin: 0 auto; text-align: center; padding: 200px 0;"><i class="fa fa-spin fa-spinner"></span>');
                        categoryid = $(this).attr('data-categoryid');
                        retrievecourses(categoryid, modalcontent, null, sourcecourseid);
                    });

                    $('.wrappermodalcontent').on('click', '.modalcourse', function () {
                        var selectedcourse = $(this);
                        var coursename = $(this).text();
                        var courseid = $(this).attr('data-courseid');
                        var sourcecourseid = wizard_target_courseid;
                        var url = $(this).attr('data-url');
                        if (courseid == sourcecourseid) {
                            require(['core/notification'], function (notification) {
                                notification.alert('Error', 'The source course must be different with the target course. Please choose another course.', 'Ok');
                            });
                        }
                        else {
                            var selected_courseids = wizard_source_courseid;
                            if($(this).hasClass('selected')){
                                wizard_source_courseid = null;
                                $(modalcontent).find('.alert-container').html('');
                                $(main_modal.getRoot()).find('.alert-container').html('');
                                $(main_modal.getRoot()).find('.previewcourse_source_course_link').text('Source Course Not Selected Yet');
                                $(main_modal.getRoot()).find('.previewcourse_source_course_link').prop('href','#');
                                $(this).toggleClass('selected');
                                if(wizard_step == 3){
                                    $(main_modal.getRoot()).find('#rollover-activity-container').addClass('hide');
                                    $(main_modal.getRoot()).find('#rollover-activity-container').removeClass('show');
                                    $('input[name="rollover-wizard-cm[]"]').prop('checked', false);
                                }
                                ajax('savesourcecourseid', {sourcecourseid: -1, mode: wizard_mode})
                            }
                            else{
                                $.ajax({
                                    type: "POST",
                                    url: M.cfg.wwwroot + "/local/rollover_wizard/ajax.php",
                                    dataType: "html",
                                    data: { action: "verifycourse", sourcecourseid: sourcecourseid, targetcourseid: courseid, mode:'reverse', sesskey: M.cfg.sesskey },
                                    beforeSend: function () {
                                        $(selectedcourse).append('<span class="verifyingcourse">&nbsp;<i class="fa fa-spin fa-spinner"></i></span>');
                                    },
                                    success: function (response) {
                                        wizard_source_courseid = courseid;
                                        var callback = null;
                                        if(response.length > 0){
                                            $(modalcontent).find('.alert-container').html(response);
                                            $(main_modal.getRoot()).find('.alert-container').html(response);
                                            $(main_modal.getRoot()).find('.previewcourse_source_course_link').text(coursename);
                                            $(main_modal.getRoot()).find('.previewcourse_source_course_link').prop('href',M.cfg.wwwroot + '/course/view.php?id='+courseid);
                                            if(wizard_step == 3){
                                                $(main_modal.getRoot()).find('#rollover-activity-container').removeClass('hide');
                                                $(main_modal.getRoot()).find('#rollover-activity-container').addClass('show');
                                                callback = function(){
                                                    modalChangeView();
                                                }
                                            }
                                        }
                                        else{
                                            $(modalcontent).find('.alert-container').html('');
                                            $(main_modal.getRoot()).find('.alert-container').html('');
                                            $(main_modal.getRoot()).find('.previewcourse_source_course_link').text('Source Course Not Selected Yet');
                                            $(main_modal.getRoot()).find('.previewcourse_source_course_link').prop('href','#');
                                            if(wizard_step == 3){
                                                $(main_modal.getRoot()).find('#rollover-activity-container').addClass('hide');
                                                $(main_modal.getRoot()).find('#rollover-activity-container').removeClass('show');
                                                $('input[name="rollover-wizard-cm[]"]').prop('checked', false);
                                            }
                                        }
                                        $.each($('.modalcourse'), function(key, item){
                                            $(this).removeClass('selected');
                                        });
                                        ajax('savesourcecourseid', {sourcecourseid: courseid, mode: wizard_mode}).then(callback);
                                    },
                                    error: function (data, response) {
                                        console.log(response);
                                    },
                                    complete: function () {
                                        $(selectedcourse).find('.verifyingcourse').remove();
                                        $(selectedcourse).toggleClass('selected');
                                    }
                                });
                            }

                        }
                    });
                }
            },
            error: function (data, response) {
                console.log(response);
            },
            complete: function () {
            }
        });
    }
    function generateRandomString(length, characters) {
        let result = '';
        const characterSet = characters || 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const characterSetLength = characterSet.length;
      
        for (let i = 0; i < length; i++) {
          const randomIndex = Math.floor(Math.random() * characterSetLength);
          result += characterSet.charAt(randomIndex);
        }
        return result;
      }
      
});