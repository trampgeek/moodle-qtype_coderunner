define(['jquery'], function($) {
    /**
     * Constructor for the NoTextArea object.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function NoTextArea(textareaId, width, height, uiParams) {
        this.textArea = $(document.getElementById(textareaId));
        this.textareaId = textareaId;
        this.html = '';
        this.uiParams = uiParams;
        this.fail = false;
        this.htmlDiv = null;
        this.wrapperNodeId = this.textareaId + "_wrapper";
        this.submitBtnId = this.textareaId.split('_')[1] + '_-submit';
        this.resetBtnId = this.textareaId.split('_')[1] + '_-resetbutton';
    }

    NoTextArea.prototype.failed = function() {
        return this.fail;
    };


    NoTextArea.prototype.failMessage = function() {
        return 'Failed to load NoTextArea UI Element';
    };

    // nothing to sync
    NoTextArea.prototype.sync = function() {};

    NoTextArea.prototype.syncIntervalSecs = function() {
        return 0; // disable autosync
    };


    NoTextArea.prototype.getElement = function() {
        let textareaSplit = this.textareaId.split('_');

        // enter something for "non-empty answer" check pass and timestamp to ensure non-duplicate answers will be passed
        if (textareaSplit[1].substring(0, 1) == 'q') {
            try {
                this.textArea.html('NoTextArea TS:' + Date.now());
            } catch (e) { }
        }

        // this code prevents preload from hiding on question settings page
        if (textareaSplit[1] != 'answerpreload') {
            try {
                let wrapperElem = document.getElementById(this.wrapperNodeId); // hide textarea
                wrapperElem.style.display = 'none';
            } catch (e) { }
        }

        // hide "Your code" text
        try {
            let promptElem = $(".prompt");
            promptElem.css('display', 'none');
        } catch (e) { }


        // hide "Reset" button if it exists (caused by having preload answer in question settings)
        try {
            let resetBtnElem = document.getElementById(this.resetBtnId);
            resetBtnElem.style.display = 'none';
        } catch (e) { }

        // change text of "Submit" button
        if (this.uiParams.submitBtnText) {
            try {
                let submitBtnElem = document.getElementById(this.submitBtnId);
                submitBtnElem.value = this.uiParams.submitBtnText;
            } catch (e) { }
        }

        return this.htmlDiv;
    };

    NoTextArea.prototype.getFields = function() {
        return '';
    };

    NoTextArea.prototype.resize = function() {};

    NoTextArea.prototype.hasFocus = function() {
        return false;
    };

    NoTextArea.prototype.destroy = function() {};

    return {
        Constructor: NoTextArea
    };
});
