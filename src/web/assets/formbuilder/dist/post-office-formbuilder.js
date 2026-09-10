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

      this.sorter.addItems($row);
      this.openField($row);
    },

    openField: function ($row) {
      this.$openRow = $row;

      var $settings = $row.find('[data-post-office-field-settings]');

      if (!this.modal) {
        this.buildModal();
      }

      // Move the row's own settings node into the modal. It goes back on close, so the
      // inputs never leave the form and never need re-syncing.
      this.$modalBody.empty().append($settings.removeClass('hidden'));
      this.modal.show();
      this.modal.updateSizeAndPosition();

      this.initHandleGenerator($settings);

      Garnish.setFocusWithin(this.$modalBody);
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
          '<div class="body"></div>' +
          '<div class="footer">' +
            '<div class="buttons left">' +
              '<button type="button" class="btn delete" data-post-office-delete-field></button>' +
            '</div>' +
            '<div class="buttons right">' +
              '<button type="button" class="btn submit"></button>' +
            '</div>' +
          '</div>' +
        '</div>'
      ).appendTo(Garnish.$bod);

      $modal.find('[data-post-office-delete-field]').text(Craft.t('post-office', 'Delete'));
      $modal.find('.submit').text(Craft.t('post-office', 'Done'));

      this.$modalBody = $modal.find('.body');

      this.modal = new Garnish.Modal($modal, {
        autoShow: false,
        hideOnEsc: true,
        hideOnShadeClick: true,
        onHide: $.proxy(this, 'closeField'),
      });

      this.addListener($modal.find('.submit'), 'activate', function () {
        this.modal.hide();
      });

      this.addListener($modal.find('[data-post-office-delete-field]'), 'activate', 'deleteField');
    },

    closeField: function () {
      if (!this.$openRow) {
        return;
      }

      var $settings = this.$modalBody.children('[data-post-office-field-settings]');

      this.$openRow.append($settings.addClass('hidden'));
      this.updateRowSummary(this.$openRow);
      this.$openRow = null;
    },

    deleteField: function () {
      if (!this.$openRow) {
        return;
      }

      var $row = this.$openRow;

      this.$openRow = null;
      this.$modalBody.empty();
      this.modal.hide();

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
