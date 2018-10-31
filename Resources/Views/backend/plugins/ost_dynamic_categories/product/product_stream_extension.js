// {block name="backend/product_stream/view/condition_list/condition_panel"}
//{namespace name=backend/plugins/ost_dynamic_categories/product_stream}

// {$smarty.block.parent}

Ext.define('Shopware.apps.OstDynamicCategories.ProductStream.view.condition_list.ConditionPanel', {
    override: 'Shopware.apps.ProductStream.view.condition_list.ConditionPanel',

    createConditionHandlers: function () {
        var me = this,
            //fetch original handlers
            handlers = me.callParent(arguments);

        //push own handler into
        handlers.push(Ext.create('Shopware.apps.OstDynamicCategories.view.condition_list.condition.TitleLiveSearchCondition'));
        handlers.push(Ext.create('Shopware.apps.OstDynamicCategories.view.condition_list.condition.DescriptionLiveSearchCondition'));
        handlers.push(Ext.create('Shopware.apps.OstDynamicCategories.view.condition_list.condition.NoCategoryLiveSearchCondition'));

        //return modified handlers array
        return handlers;
    }
});


Ext.define('Shopware.apps.OstDynamicCategories.view.condition_list.condition.TitleLiveSearchCondition', {
    extend: 'Shopware.apps.ProductStream.view.condition_list.condition.AbstractCondition',

    getName: function () {
        return 'OstDynamicCategories\\Bundle\\SearchBundle\\Condition\\SearchTitleLiveCondition';
    },

    getLabel: function () {
        return '{s name=live_title_search_condition}{/s}';
    },

    isSingleton: function () {
        return true;
    },

    create: function (callback) {
        callback(this.createField());
    },

    load: function (key, value) {
        if (key !== this.getName()) {
            return;
        }
        var field = this.createField();
        field.setValue(value);
        return field;
    },

    createField: function () {
        return Ext.create('Shopware.apps.ProductStream.view.condition_list.field.SearchTerm', {
            flex: 1,
            name: 'condition.' + this.getName()
        });
    }
});


Ext.define('Shopware.apps.OstDynamicCategories.view.condition_list.condition.DescriptionLiveSearchCondition', {
    extend: 'Shopware.apps.ProductStream.view.condition_list.condition.AbstractCondition',

    getName: function () {
        return 'OstDynamicCategories\\Bundle\\SearchBundle\\Condition\\SearchDescriptionLiveCondition';
    },

    getLabel: function () {
        return '{s name=live_description_search_condition}{/s}';
    },

    isSingleton: function () {
        return true;
    },

    create: function (callback) {
        callback(this.createField());
    },

    load: function (key, value) {
        if (key !== this.getName()) {
            return;
        }
        var field = this.createField();
        field.setValue(value);
        return field;
    },

    createField: function () {
        return Ext.create('Shopware.apps.ProductStream.view.condition_list.field.SearchTerm', {
            flex: 1,
            name: 'condition.' + this.getName()
        });
    }
});


Ext.define('Shopware.apps.OstDynamicCategories.view.condition_list.condition.NoCategoryLiveSearchCondition', {
    extend: 'Shopware.apps.ProductStream.view.condition_list.condition.AbstractCondition',

    getName: function () {
        return 'OstDynamicCategories\\Bundle\\SearchBundle\\Condition\\NoCategoryLiveCondition';
    },

    getLabel: function () {
        return '{s name=no_category_condition}{/s}';
    },

    isSingleton: function () {
        return true;
    },

    create: function (callback) {
        callback(this.createField());
    },

    load: function (key, value) {
        if (key !== this.getName()) {
            return;
        }
        return this.createField();
    },

    createField: function () {
        var me = this;

        return Ext.create('Ext.container.Container', {
            getName: function () {
                return 'condition.' + me.getName();
            },
            items: [{
                xtype: 'numberfield',
                name: 'condition.' + this.getName(),
                hidden: true,
                value: 1
            }]
        });
    }
});
// {/block}