// Override the default delete behaviour. We don't want to delete the row client side, but rather refresh
// the RHS.
TableListField.prototype.deleteRecord = function(e) {
	var img = Event.element(e);
	var link = Event.findElement(e,"a");
	var row = Event.findElement(e,"tr");

	var confirmed = confirm(ss.i18n._t('TABLEFIELD.DELETECONFIRMMESSAGE', 'Are you sure you want to delete this record?'));
	if(confirmed)
	{
		var oldImgSrc = img.getAttribute("src");
		img.setAttribute("src",'cms/images/network-save.gif'); // TODO doesn't work
		new Ajax.Request(
			link.getAttribute("href"),
			{
				method: 'post',
				postBody: 'forceajax=1' + ($('SecurityID') ? '&SecurityID=' + $('SecurityID').value : ''),
				onComplete: function(){
					// Each <td> in the row will contain <a ...>text</a>. Replace these td's with just
					// the text.
					window.location.reload();
//					$('Form_ResultsForm_CustomLanguageTranslation').refresh();
				}.bind(this),
				onFailure: this.ajaxErrorHandler
			}
		);
	}
	Event.stop(e);
}
