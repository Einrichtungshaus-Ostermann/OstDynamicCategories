
// {block name="backend/base/attribute/grid/plugin"}

// {$smarty.block.parent}

Ext.define('Shopware.form.field.OstDynamicCategories.ProductStreamGrid', {
    override: 'Shopware.form.field.ProductStreamGrid',

    createColumns: function() {
        var me = this;
        return [
            me.createSortingColumn(),
            { dataIndex: 'name', flex: 10 },
            { dataIndex: 'description', flex: 1 },
            me.createActionColumn()
        ];
    },

    createSearchField: function() {
        return Ext.create('Shopware.form.field.ProductStreamSingleSelection', this.getComboConfig());
    }
});


// {/block}
