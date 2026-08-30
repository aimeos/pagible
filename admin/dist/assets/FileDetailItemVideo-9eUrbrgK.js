import{Cn as e,J as t,K as n,Ut as r,Y as i,nt as a}from"./charts-PGn4BQj3.js";import{s as o}from"./graphql-C4Y4hVTz.js";import{t as s}from"./VIcon-c6Y80uEN.js";import{Dn as c,Qn as l,Wn as u,b as d,bt as f,jn as p,m,nn as h,vn as g}from"./index-CqZUgsl4.js";var _=o`
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
`,y={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:[`update:item`],data(){return{loading:{}}},setup(){let e=c();return{user:p(),messages:e,fileurl:u,mdiTooltipImage:h,mdiImagePlus:f}},beforeUnmount(){let e=this.$refs.video;e&&(e.pause(),e.removeAttribute(`src`),e.load()),this.loading={}},methods:{addCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let e=this.$refs.video;if(!e)return this.messages.add(this.$gettext(`No video element found`),`error`);let t=this.item.path.replace(/\.[A-Za-z0-9]+$/,`.png`).split(`/`).pop(),n=document.createElement(`canvas`),r=n.getContext(`2d`);n.width=e.videoWidth,n.height=e.videoHeight,r.drawImage(e,0,0,e.videoWidth,e.videoHeight),n.toBlob(e=>{n.width=0,n.height=0;let r=new File([e],t,{type:`image/png`});this.loading.cover=!0,this.$apollo.mutate({mutation:_,variables:{id:this.item.id,preview:r},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;g(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=l(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error saving video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::addCover(): Error saving video cover`,e)}).finally(()=>{this.loading.cover=!1})},`image/png`,1)},removeCover(){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);this.loading.cover=!0,this.item.previews={},this.$apollo.mutate({mutation:v,variables:{id:this.item.id,preview:!1}}).then(e=>{if(e.errors)throw e.errors;g(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=l(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error removing video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::removeCover(): Error removing video cover`,e)}).finally(()=>{this.loading.cover=!1})},uploadCover(e){if(this.readonly)return this.messages.add(this.$gettext(`Permission denied`),`error`);let t=e.target.files[0];if(!t)return this.messages.add(this.$gettext(`No file selected`),`error`);this.loading.cover=!0,this.$apollo.mutate({mutation:_,variables:{id:this.item.id,preview:t},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;g(this.$apollo.provider.defaultClient.cache,`files`);let t=e.data?.saveFile?.latest;t&&(this.item.previews=l(t.data)?.previews||{},this.item.updated_at=t.created_at)}).catch(e=>{this.messages.add(this.$gettext(`Error uploading video cover`)+`:
`+e,`error`),this.$log(`FileDetailItemVideo::uploadCover(): Error uploading video cover`,e)}).finally(()=>{this.loading.cover=!1})}}},b={class:`editor-container`},x=[`src`],S={key:0,class:`toolbar`},C=[`src`,`alt`],w={key:1};function T(o,c,l,u,d,f){return r(),i(`div`,b,[n(`video`,{ref:`video`,src:u.fileurl(l.item),crossorigin:`anonymous`,class:`element`,controls:``},null,8,x),l.readonly?t(``,!0):(r(),i(`div`,S,[Object.values(l.item.previews).length?(r(),i(`img`,{key:0,class:`video-preview`,src:u.fileurl(l.item,Object.values(l.item.previews).shift()),alt:l.item.name,onClick:c[0]||=e=>f.removeCover()},null,8,C)):(r(),i(`div`,w,[a(m,{icon:u.mdiTooltipImage,loading:d.loading.cover,title:o.$gettext(`Use as cover image`),class:`btn-cover-use`,onClick:c[1]||=e=>f.addCover()},null,8,[`icon`,`loading`,`title`]),a(m,{icon:``,class:`btn-cover-upload`,loading:d.loading.cover,title:o.$gettext(`Upload cover image`),onClick:c[3]||=e=>o.$refs.coverInput.click()},{default:e(()=>[a(s,{icon:u.mdiImagePlus},null,8,[`icon`]),n(`input`,{ref:`coverInput`,type:`file`,class:`cover-input`,onChange:c[2]||=e=>f.uploadCover(e)},null,544)]),_:1},8,[`loading`,`title`])]))]))])}var E=d(y,[[`render`,T],[`__scopeId`,`data-v-00bc8d8f`]]);export{E as default};