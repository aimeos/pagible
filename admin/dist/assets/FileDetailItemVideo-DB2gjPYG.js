import{d as g}from"./graphql-C9B-AmXv.js";import{_ as u,b as c,aN as p,J as f,H as w,b1 as _,b2 as $}from"./index-CE7sNWeg.js";import{u as I}from"./utils-CW0rDvqZ.js";import{b as d,k as l,q as m,l as v,p as b,A as E}from"./tree-CotDcuIv.js";import"./graphql-upload-BJJQ6Vjw.js";const h=g`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,y=g`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,C={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:["update:item"],data(){return{loading:{}}},setup(){const e=f();return{user:w(),messages:e,url:I,mdiTooltipImage:$,mdiImagePlus:_}},beforeUnmount(){const e=this.$refs.video;e&&(e.pause(),e.removeAttribute("src"),e.load()),this.loading={}},methods:{addCover(){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");const e=this.$refs.video;if(!e)return this.messages.add(this.$gettext("No video element found"),"error");const i=this.item.path.replace(/\.[A-Za-z0-9]+$/,".png").split("/").pop(),t=document.createElement("canvas"),a=t.getContext("2d");t.width=e.videoWidth,t.height=e.videoHeight,a.drawImage(e,0,0,e.videoWidth,e.videoHeight),t.toBlob(r=>{t.width=0,t.height=0;const s=new File([r],i,{type:"image/png"});this.loading.cover=!0,this.$apollo.mutate({mutation:h,variables:{id:this.item.id,preview:s},context:{hasUpload:!0}}).then(o=>{if(o.errors)throw o.errors;const n=o.data?.saveFile?.latest;n&&(this.item.previews=JSON.parse(n.data||"{}")?.previews||{},this.item.updated_at=n.created_at)}).catch(o=>{this.messages.add(this.$gettext("Error saving video cover")+`:
`+o,"error"),this.$log("FileDetailItemVideo::addCover(): Error saving video cover",o)}).finally(()=>{this.loading.cover=!1})},"image/png",1)},removeCover(){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");this.loading.cover=!0,this.item.previews={},this.$apollo.mutate({mutation:y,variables:{id:this.item.id,preview:!1}}).then(e=>{if(e.errors)throw e.errors;const i=e.data?.saveFile?.latest;i&&(this.item.previews=JSON.parse(i.data||"{}")?.previews||{},this.item.updated_at=i.created_at)}).catch(e=>{this.messages.add(this.$gettext("Error removing video cover")+`:
`+e,"error"),this.$log("FileDetailItemVideo::removeCover(): Error removing video cover",e)}).finally(()=>{this.loading.cover=!1})},uploadCover(e){if(this.readonly)return this.messages.add(this.$gettext("Permission denied"),"error");const i=e.target.files[0];if(!i)return this.messages.add(this.$gettext("No file selected"),"error");this.loading.cover=!0,this.$apollo.mutate({mutation:h,variables:{id:this.item.id,preview:i},context:{hasUpload:!0}}).then(t=>{if(t.errors)throw t.errors;const a=t.data?.saveFile?.latest;a&&(this.item.previews=JSON.parse(a.data||"{}")?.previews||{},this.item.updated_at=a.created_at)}).catch(t=>{this.messages.add(this.$gettext("Error uploading video cover")+`:
`+t,"error"),this.$log("FileDetailItemVideo::uploadCover(): Error uploading video cover",t)}).finally(()=>{this.loading.cover=!1})}}},x={class:"editor-container"},V=["src"],F={key:0,class:"toolbar"},k=["src","alt"],N={key:1};function B(e,i,t,a,r,s){return d(),l("div",x,[m("video",{ref:"video",src:a.url(t.item.path),crossorigin:"anonymous",class:"element",controls:""},null,8,V),t.readonly?E("",!0):(d(),l("div",F,[Object.values(t.item.previews).length?(d(),l("img",{key:0,class:"video-preview",src:a.url(Object.values(t.item.previews).shift()),alt:t.item.name,onClick:i[0]||(i[0]=o=>s.removeCover())},null,8,k)):(d(),l("div",N,[v(c,{icon:a.mdiTooltipImage,loading:r.loading.cover,title:e.$gettext("Use as cover image"),class:"btn-cover-use",onClick:i[1]||(i[1]=o=>s.addCover())},null,8,["icon","loading","title"]),v(c,{icon:"",class:"btn-cover-upload",loading:r.loading.cover,title:e.$gettext("Upload cover image"),onClick:i[3]||(i[3]=o=>e.$refs.coverInput.click())},{default:b(()=>[v(p,{icon:a.mdiImagePlus},null,8,["icon"]),m("input",{ref:"coverInput",type:"file",class:"cover-input",onChange:i[2]||(i[2]=o=>s.uploadCover(o))},null,544)]),_:1},8,["loading","title"])]))]))])}const A=u(C,[["render",B],["__scopeId","data-v-b650ab85"]]);export{A as default};
