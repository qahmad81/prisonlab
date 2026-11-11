const LabLinks={
    url(params){return LabI18n.pageUrl('public',params).href;},
    case(file){return this.url({case_file:file});},
    investigator(id){return this.url({investigator:id});},
    model(id,role){return this.url({model:id,role});}
};
