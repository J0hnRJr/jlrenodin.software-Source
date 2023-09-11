(function() {
  var template = Handlebars.template, templates = OCA.Impersonate = OCA.Impersonate || {};
templates['addImpersonateIcon'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<td>\n	<a class=\"action permanent impersonate\" href=\"#\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"impersonate") || (depth0 != null ? lookupProperty(depth0,"impersonate") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"impersonate","hash":{},"data":data,"loc":{"start":{"line":2,"column":57},"end":{"line":2,"column":72}}}) : helper)))
    + "\">\n		<img class=\"svg permanent action\" src=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"impersonate_src") || (depth0 != null ? lookupProperty(depth0,"impersonate_src") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"impersonate_src","hash":{},"data":data,"loc":{"start":{"line":3,"column":41},"end":{"line":3,"column":60}}}) : helper)))
    + "\" />\n	</a>\n</td>";
},"useData":true});
})();