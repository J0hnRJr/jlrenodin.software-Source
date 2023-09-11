(function() {
  var template = Handlebars.template, templates = OCA.CustomGroups.Templates = OCA.CustomGroups.Templates || {};
templates['membersInputItem'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<li class=\"user\">\n	<a>\n		<div class=\"customgroups-autocomplete-item\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"userId") || (depth0 != null ? lookupProperty(depth0,"userId") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"userId","hash":{},"data":data,"loc":{"start":{"line":3,"column":53},"end":{"line":3,"column":63}}}) : helper)))
    + "\">\n			<div class='avatardiv'></div>\n            <div class=\"autocomplete-item-text\">\n                <span class=\"autocomplete-item-displayname\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"displayName") || (depth0 != null ? lookupProperty(depth0,"displayName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"displayName","hash":{},"data":data,"loc":{"start":{"line":6,"column":60},"end":{"line":6,"column":75}}}) : helper)))
    + "</span>\n                <br/>\n                <span class=\"autocomplete-item-typeInfo\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"typeInfo") || (depth0 != null ? lookupProperty(depth0,"typeInfo") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"typeInfo","hash":{},"data":data,"loc":{"start":{"line":8,"column":57},"end":{"line":8,"column":69}}}) : helper)))
    + "</span>\n            </div>\n		</div>\n	</a>\n</li>\n";
},"useData":true});
})();
