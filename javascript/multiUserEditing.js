jQuery.entwine("multiUserEditing", function($) {
	var normalImage = 'bluedot.svg';
	var alertImage = 'reddot.svg';
	var updateTimer = 0;
	
	$('.cms-edit-form').entwine({
		RecordID: null,
		RecordClass: null,
		LockURL: null,
		
		onmatch: function() {
			this.setCurrentPage();	//start the update timer for the first time
		},

		setUpdateTimeout: function(data){
			var self = this;
			clearTimeout(updateTimer);	//clear any previous timers
			
			//set update interval approximately based on how many people are currently online
			var updateInterval = data.update.updateIntervalMultiUser * 1000;
			if (Object.keys(data).length <= 2) {
				updateInterval = data.update.updateIntervalSingleUser * 1000;	//time in ms
			}
			
			//set the next update timer
			updateTimer = setTimeout(function(){
				self.setCurrentPage();
			}, updateInterval);
		},
		
		setCurrentPage: function(){
			var self = this;
			
			//check that we are in the pages section of the site
			var pageID = this.find('#Form_EditForm_ID').val();
			if (typeof(pageID) !== 'undefined' && pageID !== false && pageID !== 0 && pageID !== '0') {
				
				$.getJSON('admin/editing/set/' + pageID, function (data) {
					self.updateUserLabels(data, pageID);
					self.setUpdateTimeout(data);	//update every x seconds
				});
			}
		},
		
		updateUserLabels: function(data, currentPageID){
			//remove all existing labels
			var multipleEditors = false;
			var cmsTree = $('.cms-tree');
			var cmsMain = $('#Root');
			cmsTree.find('.user-label').remove();
			cmsMain.find('.multi-user-editing-alert-message').remove();
			
			//reorganise data into an array for each pageID;
			var organisedData = {};
			$.each(data, function (index, item) {
				if (index !== 'update') {	//filter out the array key containing update intervals
					if (typeof(organisedData[item.pageID]) === 'undefined') {
						organisedData[item.pageID] = [];
					}

					organisedData[item.pageID].push(item);
				}
			});

			//add new labels
			$.each(organisedData, function (pageID, itemsArray) {
				var treeItem = cmsTree.find('li[data-id=' + pageID + ']');

				var dot = normalImage;
				if (itemsArray.length > 1) {	//more than one editors on a single page, use a red dot to indicate
					dot = alertImage;
				}

				var users = '';	//list of all users editing the current page
				var usersBR = '<ul style="margin-left:30px; list-style:inherit;">';	//list of all users separated by br instead of newline
				$.each(itemsArray, function (index, item) {
					if (index !== 0) {
						users += "\n";	//separate users by new line
						//usersBR += "<br/>";
					}
					var userLine = item.fullName + ' &lt;' + item.email + '&gt;';
					users += userLine;
					usersBR += "<li>" + userLine + "</li>";
				});
				usersBR += "</ul>";

				var dotHTML = '<div style="position:relative; float:right;" class="user-label" title="' + users +
					'"><img height="10px" width="10px" ' + 'src="multiuser-editing/images/' + dot + '"/></div>';
					treeItem.find('.jstree-icon').first().after(dotHTML);

				//append a highly visible message to the current page, if multiple editors are editing the same page
				if (pageID === currentPageID && dot === alertImage) {

					var messageHTML = '<div class="multi-user-editing-alert-message message error" style="margin-bottom:-16px;">' +
						'<div style="float:left;" title="' + users + '">' +
						'<img height="10px" width="10px" ' + 'src="multiuser-editing/images/' + dot + '"/>&nbsp; </div>' +
						'<div><strong>Warning</strong>: the following users are currently editing this page:<br/>' +
						usersBR + '</div></div>';
					cmsMain.prepend(messageHTML);
				}
			});

			return multipleEditors;
		},
		
		onunmatch: function(){
			clearTimeout(updateTimer);
		}
	});
});


