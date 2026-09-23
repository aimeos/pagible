import{Wt as e,X as t,Y as n,q as r,rt as i,wn as a}from"./charts-BFwsnyip.js";import{s as o}from"./graphql-CNGOqxVc.js";import{v as s}from"./i18n-BIsbIWbx.js";import{$ as c,Lt as l}from"./mdi-DjcIfWd7.js";import{t as u}from"./VIcon-Db85FLnB.js";import{H as d,O as f,R as p,S as m,T as h,rt as g}from"./index-C9RuzODM.js";var _=o`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,v=o`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        data
        created_at
      }
    }
  }
`,y={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:[`update:item`],data(){return{loading:{}}},setup(){let e=p();return{user:d(),messages:e,fileurl:g,mdiTooltipImage:l,mdiImagePlus:c}},beforeUnmount(){let e=this.$refs.video;e&&(e.pause(),e.removeAttribute(`src`),e.load()),this.loading={}},methods:{addCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let e=this.$refs.video;if(!e)return this.messages.add(this.$gettext(`No video element found`),`error`);let t=this.item.path.replace(/\.[A-Za-z0-9]+$/,`.png`).split(`/`).pop(),n=document.createElement(`canvas`),r=n.getContext(`2d`);n.width=e.videoWidth,n.height=e.videoHeight,r.drawImage(e,0,0,e.videoWidth,e.videoHeight),n.toBlob(e=>{n.width=0,n.height=0;let r=new File([e],t,{type:`image/png`});this.loading.cover=!0,this.$apollo.mutate({mutation:_,variables:{id:this.item.id,preview:r},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;f(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=s(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error saving video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::addCover(): Error saving video cover`,e)}).finally(()=>{this.loading.cover=!1})},`image/png`,1)},removeCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);this.loading.cover=!0,this.item.previews={},this.$apollo.mutate({mutation:v,variables:{id:this.item.id,preview:!1}}).then(e=>{if(e.errors)throw e.errors;f(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=s(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error removing video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::removeCover(): Error removing video cover`,e)}).finally(()=>{this.loading.cover=!1})},uploadCover(e){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let t=e.target.files[0];if(!t)return this.messages.add(this.$gettext(`No file selected`),`error`);this.loading.cover=!0,this.$apollo.mutate({mutation:_,variables:{id:this.item.id,preview:t},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;f(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=s(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error uploading video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::uploadCover(): Error uploading video cover`,e)}).finally(()=>{this.loading.cover=!1})}}},b={class:`editor-container`},x=[`src`],S={key:0,class:`toolbar`},C=[`src`,`alt`],w={key:1};function T(o,s,c,l,d,f){return e(),t(`div`,b,[r(`video`,{ref:`video`,src:l.fileurl(c.item),crossorigin:`anonymous`,class:`element`,controls:``},null,8,x),c.readonly?n(``,!0):(e(),t(`div`,S,[Object.values(c.item.previews).length?(e(),t(`img`,{key:0,class:`video-preview`,src:l.fileurl(c.item,Object.values(c.item.previews).shift()),alt:c.item.name,onClick:s[0]||=e=>f.removeCover()},null,8,C)):(e(),t(`div`,w,[i(m,{icon:l.mdiTooltipImage,loading:d.loading.cover,title:o.$gettext(`Use as cover image`),class:`btn-cover-use`,onClick:s[1]||=e=>f.addCover()},null,8,[`icon`,`loading`,`title`]),i(m,{icon:``,class:`btn-cover-upload`,loading:d.loading.cover,title:o.$gettext(`Upload cover image`),onClick:s[3]||=e=>o.$refs.coverInput.click()},{default:a(()=>[i(u,{icon:l.mdiImagePlus},null,8,[`icon`]),r(`input`,{ref:`coverInput`,type:`file`,class:`cover-input`,onChange:s[2]||=e=>f.uploadCover(e)},null,544)]),_:1},8,[`loading`,`title`])]))]))])}var E=h(y,[[`render`,T],[`__scopeId`,`data-v-00bc8d8f`]]);export{E as default};