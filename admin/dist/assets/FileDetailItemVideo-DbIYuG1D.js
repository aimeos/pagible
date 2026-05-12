import{d as g}from"./graphql-C9B-AmXv.js";import{_ as u,a as c,az as p,H as f,G as w,aP as _,aQ as $}from"./index-Dj2h6P6L.js";import{u as I}from"./utils-GbLosTlN.js";import{b as d,k as l,q as m,l as v,p as E,A as y}from"./tree-BnNcOl7f.js";import"./graphql-upload-BJJQ6Vjw.js";const h=g`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,C=g`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,b={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:["update:item"],data(){return{loading:{}}},setup(){const e=f();return{user:w(),messages:e,url:I,mdiTooltipImage:$,mdiImagePlus:_}},beforeUnmount(){const e=this.$refs.video;e&&(e.pause(),e.removeAttribute("src"),e.load()),this.loading={}},methods:{addCover(){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");const e=this.$refs.video;if(!e)return this.messages.add(this.$gettext("No video element found"),"error");const i=this.item.path.replace(/\.[A-Za-z0-9]+$/,".png").split("/").pop(),t=document.createElement("canvas"),o=t.getContext("2d");t.width=e.videoWidth,t.height=e.videoHeight,o.drawImage(e,0,0,e.videoWidth,e.videoHeight),t.toBlob(r=>{t.width=0,t.height=0;const s=new File([r],i,{type:"image/png"});this.loading.cover=!0,this.$apollo.mutate({mutation:h,variables:{id:this.item.id,preview:s},context:{hasUpload:!0}}).then(a=>{if(a.errors)throw a.errors;const n=a.data?.saveFile?.latest;n&&(this.item.previews=JSON.parse(n.data||"{}")?.previews||{},this.item.updated_at=n.created_at)}).catch(a=>{this.messages.add(this.$gettext("Error saving video cover")+`:
`+a,"error"),this.$log("FileDetailItemVideo::addCover(): Error saving video cover",a)}).finally(()=>{this.loading.cover=!1})},"image/png",1)},removeCover(){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");this.loading.cover=!0,this.item.previews={},this.$apollo.mutate({mutation:C,variables:{id:this.item.id,preview:!1}}).then(e=>{if(e.errors)throw e.errors;const i=e.data?.saveFile?.latest;i&&(this.item.previews=JSON.parse(i.data||"{}")?.previews||{},this.item.updated_at=i.created_at)}).catch(e=>{this.messages.add(this.$gettext("Error removing video cover")+`:
`+e,"error"),this.$log("FileDetailItemVideo::removeCover(): Error removing video cover",e)}).finally(()=>{this.loading.cover=!1})},uploadCover(e){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");const i=e.target.files[0];if(!i)return this.messages.add(this.$gettext("No file selected"),"error");this.loading.cover=!0,this.$apollo.mutate({mutation:h,variables:{id:this.item.id,preview:i},context:{hasUpload:!0}}).then(t=>{if(t.errors)throw t.errors;const o=t.data?.saveFile?.latest;o&&(this.item.previews=JSON.parse(o.data||"{}")?.previews||{},this.item.updated_at=o.created_at)}).catch(t=>{this.messages.add(this.$gettext("Error uploading video cover")+`:
`+t,"error"),this.$log("FileDetailItemVideo::uploadCover(): Error uploading video cover",t)}).finally(()=>{this.loading.cover=!1})}}},x={class:"editor-container"},V=["src"],F={key:0,class:"toolbar"},k=["src","alt"],N={key:1};function P(e,i,t,o,r,s){return d(),l("div",x,[m("video",{ref:"video",src:o.url(t.item.path),crossorigin:"anonymous",class:"element",controls:""},null,8,V),t.readonly?y("",!0):(d(),l("div",F,[Object.values(t.item.previews).length?(d(),l("img",{key:0,class:"video-preview",src:o.url(Object.values(t.item.previews).shift()),alt:t.item.name,onClick:i[0]||(i[0]=a=>s.removeCover())},null,8,k)):(d(),l("div",N,[v(c,{icon:o.mdiTooltipImage,loading:r.loading.cover,title:e.$gettext("Use as cover image"),class:"btn-cover-use",onClick:i[1]||(i[1]=a=>s.addCover())},null,8,["icon","loading","title"]),v(c,{icon:"",class:"btn-cover-upload",loading:r.loading.cover,title:e.$gettext("Upload cover image"),onClick:i[3]||(i[3]=a=>e.$refs.coverInput.click())},{default:E(()=>[v(p,{icon:o.mdiImagePlus},null,8,["icon"]),m("input",{ref:"coverInput",type:"file",class:"cover-input",onChange:i[2]||(i[2]=a=>s.uploadCover(a))},null,544)]),_:1},8,["loading","title"])]))]))])}const A=u(b,[["render",P],["__scopeId","data-v-b650ab85"]]);export{A as default};
