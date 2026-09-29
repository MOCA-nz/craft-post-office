/**
 * Post Office form builder.
 *
 * Each field row carries its own settings markup, hidden, inside the row itself. Opening a
 * field moves that node into a Garnish modal and moves it back on close. Nothing is
 * serialised by hand: the inputs are already inside the form, already correctly named, so
 * they post exactly like any other Craft form, and reordering rows reorders the posted
 * fields because PHP preserves POST key order.
 */
(function ($) {
  'use strict';

  Craft.PostOfficeFormBuilder = Garnish.Base.extend({
    $container: null,
    $list: null,
    $addBtn: null,
    $prototype: null,
    modal: null,
    $modalBody: null,
    $openRow: null,
    rowCounter: 0,

    /**
     * Set by saveField() only. Every other way out of the modal (the close button, ESC, a
     * click on the shade) leaves it false, so hiding the modal cancels by default.
     */
    committing: false,

    /**
     * The open row's input values as they were when the modal opened, so a cancel can put
     * them back. The inputs live in the page's form, so editing them is immediate: there is
     * nothing to "not save" unless the previous values are kept.
     */
    restorePoint: null,

    init: function (container) {
      this.$container = $(container);
      this.$list = this.$container.find('[data-post-office-field-list]');
      this.$addBtn = this.$container.find('[data-post-office-add-field]');
      this.$prototype = this.$container.find('[data-post-office-field-prototype]');

      this.addListener(this.$addBtn, 'activate', 'addField');

      // Delegated plain click, not Garnish's `activate`: `activate` is triggered on the
      // element it is bound to, so it never arrives from a child row. The row opener is a
      // real <button>, so a click listener also covers Enter and Space for free.
      this.addListener(this.$list, 'click', function (ev) {
        var $opener = $(ev.target).closest('[data-post-office-field-open]');

        if (!$opener.length) {
          return;
        }

        ev.preventDefault();
        this.openField($opener.closest('[data-post-office-field]'));
      });

      this.initSort();
    },

    initSort: function () {
      this.sorter = new Garnish.DragSort(this.$list.children('[data-post-office-field]'), {
        handle: '.move',
        axis: Garnish.Y_AXIS,
        magnetStrength: 4,
        helperLagBase: 1.5,
      });
    },

    addField: function () {
      var rowId = 'row' + ++this.rowCounter;
      var html = this.$prototype.html().replace(/__ROW__/g, rowId);
      var $row = $(html).appendTo(this.$list);

      // The row came out of a <script> template, so none of Craft's own widgets in it are
      // alive yet. Without this the lightswitches are inert markup: clicking Required does
      // nothing, and its hidden input never changes, until the page is reloaded from a save.
      Craft.initUiElements($row);

      this.sorter.addItems($row);
      this.openField($row);
    },

    openField: function ($row) {
      this.$openRow = $row;

      var $settings = $row.find('[data-post-office-field-settings]');

      if (!this.modal) {
        this.buildModal();
      }

      this.restorePoint = this.takeRestorePoint($settings);

      // Move the row's own settings node into the modal. It goes back on close, so the
      // inputs never leave the form and never need re-syncing.
      this.$modalBody.empty().append($settings.removeClass('hidden'));
      this.modal.show();
      this.modal.updateSizeAndPosition();

      this.initHandleGenerator($settings);

      Garnish.setFocusWithin(this.$modalBody);
    },

    /**
     * Records the current value of every input in the field's settings.
     *
     * Lightswitches are recorded through their own widget rather than their hidden input,
     * because putting the input's value back would leave the switch itself showing the
     * opposite state.
     */
    takeRestorePoint: function ($settings) {
      var values = [];
      var switches = [];

      $settings.find('input, select, textarea').each(function () {
        values.push({el: this, value: $(this).val()});
      });

      $settings.find('.lightswitch').each(function () {
        var lightswitch = $(this).data('lightswitch');

        if (lightswitch) {
          switches.push({lightswitch: lightswitch, on: lightswitch.on});
        }
      });

      return {values: values, switches: switches};
    },

    /**
     * Puts the recorded values back, undoing whatever was typed while the modal was open.
     */
    applyRestorePoint: function (restorePoint) {
      if (!restorePoint) {
        return;
      }

      restorePoint.values.forEach(function (entry) {
        $(entry.el).val(entry.value);
      });

      // After the raw values, so each switch rewrites its own hidden input last.
      restorePoint.switches.forEach(function (entry) {
        if (entry.on) {
          entry.lightswitch.turnOn(true);
        } else {
          entry.lightswitch.turnOff(true);
        }
      });
    },

    /**
     * Derives the handle from the label as it is typed, the way every other Craft screen does.
     *
     * Craft's generator stops as soon as the handle is edited by hand, and never touches a
     * handle that already has a value, so an existing field's handle is safe: changing its
     * label will not silently rename it and orphan the stored values.
     */
    initHandleGenerator: function ($settings) {
      if ($settings.data('handleGenerator')) {
        return;
      }

      var $label = $settings.find('[data-post-office-input="label"]');
      var $handle = $settings.find('[data-post-office-input="handle"]');

      if (!$label.length || !$handle.length) {
        return;
      }

      $settings.data('handleGenerator', new Craft.HandleGenerator($label, $handle));
    },

    buildModal: function () {
      var $modal = $(
        '<div class="modal post-office-field-modal">' +
          '<button type="button" class="post-office-field-modal-close" data-icon="remove" data-post-office-close-field></button>' +
          '<div class="body"></div>' +
          '<div class="footer">' +
            '<div class="buttons left">' +
              '<button type="button" class="btn delete" data-post-office-delete-field></button>' +
            '</div>' +
            '<div class="buttons right">' +
              '<button type="button" class="btn submit" data-post-office-save-field></button>' +
            '</div>' +
          '</div>' +
        '</div>'
      ).appendTo(Garnish.$bod);

      $modal.find('[data-post-office-close-field]').attr('aria-label', Craft.t('post-office', 'Close'));
      $modal.find('[data-post-office-delete-field]').text(Craft.t('post-office', 'Delete'));
      $modal.find('[data-post-office-save-field]').text(Craft.t('post-office', 'Save'));

      this.$modalBody = $modal.find('.body');

      this.modal = new Garnish.Modal($modal, {
        autoShow: false,
        hideOnEsc: true,
        hideOnShadeClick: true,
        onHide: $.proxy(this, 'onModalHide'),
      });

      this.addListener($modal.find('[data-post-office-save-field]'), 'activate', 'saveField');
      this.addListener($modal.find('[data-post-office-close-field]'), 'activate', 'cancelField');
      this.addListener($modal.find('[data-post-office-delete-field]'), 'activate', 'deleteField');
    },

    /**
     * Keeps what was typed, and closes.
     */
    saveField: function () {
      this.committing = true;
      this.modal.hide();
    },

    /**
     * Closes without keeping anything. Also what ESC and a click on the shade do.
     */
    cancelField: function () {
      this.committing = false;
      this.modal.hide();
    },

    onModalHide: function () {
      var committing = this.committing;

      this.committing = false;

      if (!this.$openRow) {
        return;
      }

      if (committing) {
        this.commitField();

        return;
      }

      this.discardField();
    },

    commitField: function () {
      var $settings = this.$modalBody.children('[data-post-office-field-settings]');

      this.$openRow.append($settings.addClass('hidden'));
      // The row has been through the modal at least once now, so cancelling a later edit
      // puts the old values back rather than throwing the field away.
      this.$openRow.removeAttr('data-post-office-new');
      this.updateRowSummary(this.$openRow);
      this.$openRow = null;
      this.restorePoint = null;
    },

    discardField: function () {
      var $row = this.$openRow;

      this.$openRow = null;

      // A field that has never been saved has nothing to go back to, so cancelling it means
      // the field itself goes away.
      if ($row.is('[data-post-office-new]')) {
        this.$modalBody.empty();
        this.restorePoint = null;
        this.removeRow($row);

        return;
      }

      var $settings = this.$modalBody.children('[data-post-office-field-settings]');

      this.applyRestorePoint(this.restorePoint);
      this.restorePoint = null;

      $row.append($settings.addClass('hidden'));
      this.updateRowSummary($row);
    },

    deleteField: function () {
      if (!this.$openRow) {
        return;
      }

      var $row = this.$openRow;

      this.$openRow = null;
      this.restorePoint = null;
      this.$modalBody.empty();
      this.modal.hide();

      this.removeRow($row);
    },

    removeRow: function ($row) {
      this.sorter.removeItems($row);
      $row.remove();
    },

    /**
     * Reflects the field's label and type back onto the collapsed row.
     */
    updateRowSummary: function ($row) {
      var label = $row.find('[data-post-office-input="label"]').val() || Craft.t('post-office', 'Untitled field');
      var $type = $row.find('[data-post-office-input="type"]');
      var typeLabel = $type.find('option:selected').text();
      var handle = $row.find('[data-post-office-input="handle"]').val();

      $row.find('[data-post-office-row-label]').text(label);
      $row.find('[data-post-office-row-meta]').text(handle ? typeLabel + ' · ' + handle : typeLabel);

      // Craft's lightswitch keeps its value in a hidden input alongside the switch, so
      // match on the name suffix rather than trying to attribute the switch itself.
      var required = $row.find('input[name$="[required]"]').val() === '1';
      $row.find('[data-post-office-row-required]').toggleClass('hidden', !required);
    },

    destroy: function () {
      if (this.modal) {
        this.modal.destroy();
      }

      this.base();
    },
  });
})(jQuery);
