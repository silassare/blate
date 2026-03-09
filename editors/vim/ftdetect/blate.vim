" Vim/Neovim file type detection for Blate templates.
" Drop this file in ~/.vim/ftdetect/ (Vim) or
" ~/.config/nvim/ftdetect/ (Neovim), or let a plugin manager handle it.

autocmd BufNewFile,BufRead *.blate setfiletype blate
