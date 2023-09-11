(function() {
  var template = Handlebars.template, templates = OCA.Impersonate = OCA.Impersonate || {};
templates['impersonateNotification'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div id=\"impersonate-notification\"\n	<div class=\"row\">\n		<a href=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"docUrl") || (depth0 != null ? lookupProperty(depth0,"docUrl") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"docUrl","hash":{},"data":data,"loc":{"start":{"line":3,"column":11},"end":{"line":3,"column":21}}}) : helper)))
    + "\" style=\"text-align: center;\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"displayText") || (depth0 != null ? lookupProperty(depth0,"displayText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"displayText","hash":{},"data":data,"loc":{"start":{"line":3,"column":51},"end":{"line":3,"column":66}}}) : helper)))
    + "</a>\n	</div>\n</div>";
},"useData":true});
})();